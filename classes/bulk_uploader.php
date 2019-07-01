<?php
// This file is part of moodle-local_assignbulk - https://github.com/gormster/moodle-local_assignbulk/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * An uploader handles the work of uploading one lot of assignment submissions.
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignbulk;

use moodle_exception;
use coding_exception;
use assign;
use context_user;
use stored_file;
use stdClass;
use moodle_url;

use \assignfeedback_editpdf\document_services;
use \assignfeedback_editpdf\combined_document;
use \assignfeedback_editpdf\pdf;
use \core_files\conversion;

defined('MOODLE_INTERNAL') or die();

/**
 * Handles the work of uploading one lot of assignment submissions.
 *
 * The uploader class does the work of uploading one lot of assignment submissions.
 * It handles this through the public execute() function, which takes care of both
 * preflight and committing uploads.
 *
 * It also contains some convenient functions useful outside of the upload process,
 * such as preparing a draft area for upload.
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_uploader {

    /**
     * The assignment we're going to submit to
     * @var assign
     */
    private $assign;

    /**
     * The identifying field in the user record with which we match filenames
     * @var string
     */
    private $ident;

    /**
     * A convenient container for our staging file area's identifying fields
     * @var array [$contextid, 'local_assignbulk', 'staging', $itemid]
     */
    private $staging;

    /**
     * A return URL to pass to any errors we might throw
     * @var moodle_url
     */
    private $errorurl;

    /**
     * Should the current execution operation actually send the files to the assignment?
     * If false, this is effectively a dry run - no files will be submitted.
     * @var boolean
     */
    private $commit;

    /**
     * The pre-rendered images for PDF-compatible files. Keyed by the ORIGINAL file location
     * (after unzip_top_level_files).
     * @var [string filepath => => stored_file rendered_page_dir]
     */
    private $rawimages;

    /**
     * Since rawimages is keyed by the original file path, we need to keep track of those.
     * @var [string filepath => string contenthash]
     */
    private $filepaths;

    /**
     * Progress instance
     * @var \core\progress\base
     */
    private $progress;

    /**
     * Constructor.
     * @param assign $assign Assignment instance
     * @param string $ident  The identifying mark for a user; the field in a user record which
     *                       should be used to identify submission files.
     */
    public function __construct(assign $assign, $ident) {
        $this->assign = $assign;
        $this->ident = $ident;
        $id = $assign->get_course_module()->id;
        $this->errorurl = new moodle_url('local/assignbulk/upload.php', ['id' => $id]);
        $this->rawimages = [];
    }

    /**
     * Copy files from a given draft area to their matching assignment submissions.
     * @param  int     $draftitemid The itemid of a draft area which contains the files to be uploaded
     * @param  boolean $commit      If false, do a dry run without actually committing the files to the submissions
     * @return stdClass             An object contaning the following fields:
     *  users: array[] An array of arrays with keys fullname, id, notices (string[]) and submissions (array[])
     *  warnings: string[] The paths of files that weren't matched to any user
     */
    public function execute($draftitemid, $commit = true, \core\progress\base $progress = null) {

        $feedback = new stdClass();
        if (empty($progress)) {
            $progress = new \core\progress\none();
        }
        $this->progress = $progress;

        $this->commit = $commit;

        $this->progress->start_progress('Uploading', 6);

        // 1. Copy files to staging area.
        $this->save_draft_files($draftitemid);

        // 2. Unzip any top level compressed files that don't match the naming scheme.
        $this->unzip_top_level_files();

        // 3. Walk the tree.
        $this->walk_step('/');

        // 4. Submit the files for the users.
        $feedback->users = $this->submit_user_files();

        // 5. Make a note of the remaining non-directory contents of the staging area to report to the user.
        $feedback->warnings = $this->remaining_unstaged_files();
        $this->progress->increment_progress();

        // 6. Delete the contents of the staging area.
        if (empty($feedback->warnings)) {
            $this->delete_staging_area();
        }
        $this->progress->increment_progress();

        $this->progress->end_progress();

        return $feedback;

    }

    /**
     * Save the files from the draft area to a staging area where we will unpack them
     * This is part of a two step process that we use simply to avoid messing with the
     * draft area - if something fails, we want the draft area to be untouched.
     * @param  int     $draftitemid The draft item ID we received from the upload form
     * @param  boolean $delete      Unused, for now
     */
    protected function save_draft_files($draftitemid, $delete = false) {
        global $USER;

        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        // Clear the area first.
        $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        $fs->delete_area_files($contextid, 'local_assignbulk', 'submissions');

        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id');

        $change = new stdClass();
        $change->contextid = $contextid;
        $change->component = 'local_assignbulk';
        $change->filearea = 'staging';

        $this->progress->start_progress('Saving to staging area', count($files));
        foreach ($files as $file) {
            $fs->create_file_from_storedfile($change, $file);
            $this->progress->increment_progress();
        }
        $this->progress->end_progress();

        $this->staging = [$change->contextid, $change->component, $change->filearea, $draftitemid];
    }

    /**
     * Extract any zip files at the top level that do NOT match a valid user identifier. This
     * only works at the top level; nested zip files are not extracted.
     */
    protected function unzip_top_level_files() {
        $fs = get_file_storage();
        $s = $this->staging;
        $topfiles = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], '/', false, false);

        $this->progress->start_progress('Unzipping top-level files', count($topfiles));
        foreach ($topfiles as $file) {

            // If the item is not a compressed file, continue.
            $mimetype = $file->get_mimetype();
            $packer = get_file_packer($mimetype);
            if (empty($packer)) {
                $this->progress->increment_progress();
                continue;
            }

            // If the item matches the naming scheme, continue.
            $filename = $this->effective_file_name($file);
            if (!empty($this->user_for_ident($filename, false))) {
                $this->progress->increment_progress();
                continue;
            }

            // Unzip the item in place.
            $pathbase = '/' . $file->get_filename() . '/';
            $fs->create_directory($s[0], $s[1], $s[2], $s[3], $pathbase);
            $fileprogress = new unzip_progress($this->progress, 'Unzipping ' . $file->get_filename());
            $results = $file->extract_to_storage($packer, $s[0], $s[1], $s[2], $s[3], $pathbase, null, $fileprogress);
            unset($fileprogress);

            // If unzipping the file causes any overwritten files, throw an exception.
            foreach ($results as $filename => $result) {
                if ($result != true) {
                    throw new moodle_exception('local_assignbulk', 'unpackerror', $this->errorurl, $result);
                }
            }

            // Delete the zip file so it doesn't show up as a leftover when
            // checking the remaining contents of the staging area.
            $file->delete();
        }
        $this->progress->end_progress();
    }

    /**
     * A single step in walking the tree of the staging area. Calls itself recursively for all the subdirectories.
     * Copies the files it finds to the user's directory in the submissions area (but doesn't actually submit them yet)
     * Breadth first! Files in higher directories get priority over files in lower directories.
     * @param  string $directory The path of the directory to walk over
     */
    protected function walk_step($directory) {
        $fs = get_file_storage();
        $s = $this->staging;
        $files = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], $directory, false, true);

        $subpaths = [];

        $this->progress->start_progress('Walking over '.$directory, count($files));
        foreach ($files as $file) {

            // This is necessary to handle rawImages for one-file submissions.
            if ($filepath = $this->is_raw_images($file)) {
                if ($file->is_directory()) {
                    $this->rawimages[$filepath] = $file;
                }
                $this->progress->increment_progress();
                continue;
            }

            $filename = $this->effective_file_name($file);

            $user = $this->user_for_ident($filename, false);
            if (!empty($user)) {
                $this->copy_to_userdir($file, $user);
                $this->progress->increment_progress();
                continue;
            }

            if ($file->is_directory()) {
                $subpaths[] = $file->get_filepath();
                continue; // Don't increment the progress, it will be handled by the next loop.
            }

            $this->progress->increment_progress();
        }

        foreach ($subpaths as $subpath) {
            $this->walk_step($subpath);
        }
        $this->progress->end_progress();
    }

    /**
     * Submits the files from the user directories in the submissions area to the assignment.
     * Or it pretends to, if execute() was called with $commit == false.
     * @return array The user-specific described in {@link bulk_uploader::execute()}
     */
    protected function submit_user_files() {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $submissions = [];
        $allfiles = $fs->get_area_files($contextid, 'local_assignbulk', 'submissions', false, 'itemid, filepath, filename', false);
        foreach ($allfiles as $file) {
            $itemid = $file->get_itemid();
            if (!isset($submissions[$itemid])) {
                $submissions[$itemid] = [];
            }
            $submissions[$itemid][] = $file;
        }

        // Create a reverse lookup array for idents.
        if (!isset($this->_useridents)) {
            $this->_init_useridents(); // @codeCoverageIgnore
        }

        $useridentsreverse = [];
        foreach ($this->_useridents as $userident => $user) {
            $useridentsreverse[$user->id] = $userident;
        }

        $this->progress->start_progress('Creating submissions', count($submissions));

        $feedback = [];
        foreach ($submissions as $userid => $files) {
            $userident = $useridentsreverse[$userid];
            $user = $this->user_for_ident($userident);
            $this->simplify_user_paths($files, $userident);
            $this->delete_empty_directories($files, $userid);

            if ($this->commit) {
                $feedback[] = $this->push_files_to_assign($files, $user);
            } else {
                // Mock up preview feedback.
                $submissions = [];
                foreach ($files as $file) {
                    $filename = $this->filepaths[$file->get_contenthash()];
                    $submissions[] = [
                        'filename' => $file->get_filepath() . $file->get_filename(),
                        'rawImages' => array_key_exists($filename, $this->rawimages)
                    ];
                }
                $feedback[] = ['fullname' => fullname($user), 'id' => $user->id, 'submissions' => $submissions];
            }
            $this->progress->increment_progress();
        }
        $this->progress->end_progress();

        return $feedback;

    }

    /**
     * After we've submitted all the user files, any files left in the staging area have not been submitted.
     * We use this function to warn the user that they weren't processed (maybe the name is mis-spelled).
     * @return array The warnings described in {@link bulk_uploader::execute()}
     */
    protected function remaining_unstaged_files() {
        $fs = get_file_storage();
        $s = $this->staging;

        $staging = $fs->get_area_files($s[0], $s[1], $s[2], $s[3]);
        $remaining = [];
        foreach ($staging as $file) {
            if ($file->is_directory()) {
                continue;
            }
            if ($this->is_raw_images($file)) {
                continue;
            }

            $remaining[] = $file->get_filepath() . $file->get_filename();
        }

        return $remaining;
    }

    /**
     * Be good citizens - delete our files after we're no longer using them
     */
    protected function delete_staging_area() {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;
        $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        $fs->delete_area_files($contextid, 'local_assignbulk', 'submissions');
    }

    /**
     * Reduce the path length if possible. Makes things like /submissions.zip/question1/user01.txt into /question1.txt
     * @param  stored_file[] $files         the files we're trying to simplify
     * @param  string        $userident     the identifying feature of the user
     */
    protected function simplify_user_paths($files, $userident) {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        // 1. Find the longest common prefix.
        $prefix = explode('/', trim($files[0]->get_filepath(), '/'));
        $pathlen = count($prefix);
        $samename = true; // This is for step 2!

        foreach ($files as $file) {
            $path = explode('/', trim($file->get_filepath(), '/'));

            // Find the longest common prefix of the two arrays, and slice off $prefix at the point where they differ.
            for ($i = 0; $i < count($prefix); $i++) {
                if ($prefix[$i] == $path[$i]) {
                    continue;
                }
                array_splice($prefix, $i);
                break;
            }

            if (empty($prefix)) {
                break; // No point continuing!
            }
            if ($samename &&
                (($this->effective_file_name($file) != $userident) ||
                (count($path) != $pathlen))) {
                $samename = false;
            }
        }

        // If the shortest prefix was /, then $prefix == [''].
        if (!empty($prefix) && $prefix !== ['']) {
            $dropstr = '/' . implode('/', $prefix);
            $droplen = strlen($dropstr);
            foreach ($files as $file) {
                if (substr($file->get_filepath(), 0, $droplen) != $dropstr) {
                    throw new coding_exception('Assertion failure: file path does not begin with prefix string'); // @codeCoverageIgnore
                }
                $newpath = substr($file->get_filepath(), $droplen);
                $file->rename($newpath, $file->get_filename());
            }

            $pathlen -= count($prefix);
        }

        // 2. If the files are all named the same thing, rename them to their directory's name.
        if (count($files) > 1 && $samename) {
            // We actually have to quickly iterate to see if there is some path component that differs for all the files,
            // otherwise we'll end up with name clashes.
            $pathcomponents = array_fill(0, $pathlen, []);
            foreach ($files as $file) {
                $path = explode('/', trim($file->get_filepath(), '/'));
                if (count($path) != $pathlen) {
                    throw new coding_exception('Assertion failure: paths should all be the same length by this point'); // @codeCoverageIgnore
                }
                for ($i = 0; $i < $pathlen; $i++) {
                    $pathcomponents[$i][] = $path[$i];
                }
            }

            $varindex = -1;
            $commonsuffix = null;
            foreach ($pathcomponents as $i => $comps) {
                if (count(array_unique($comps)) == count($files)) {
                    // There is a unique component at this index for ALL files.
                    $varindex = $i;
                    $commonsuffix = true;
                } else if (($varindex > -1) && (count(array_unique($comps)) > 1)) {
                    // Uh oh - some files after the unique component have different names, too!
                    // That means we can't drop the suffix because we risk losing information.
                    $commonsuffix = false;
                }
            }

            if (($varindex > -1) && $commonsuffix) {
                foreach ($files as $file) {
                    $extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                    $path = explode('/', trim($file->get_filepath(), '/'));
                    $path = array_slice($path, 0, $varindex + 1);
                    $newname = array_pop($path) . '.' . $extension;
                    $newpath = '/' . implode('/', $path) . '/';
                    $file->rename($newpath, $newname);
                }
            }
        }
    }

    /**
     * We don't want to submit empty directories to the assignment, so remove any that might have been left over after
     * simplifying the user paths. We don't do this at the same time as simplify_user_paths because that function is complex
     * enough already.
     * @param  stored_file[] $files  The files that are being submitted for this user
     * @param  int           $userid The user id
     */
    protected function delete_empty_directories($files, $userid) {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $inuse = [];
        foreach ($files as $file) {
            $path = explode('/', rtrim($file->get_filepath(), '/'));
            while (count($path)) {
                $p = implode('/', $path) . '/';
                $inuse[$p] = true;
                array_pop($path);
            }
        }

        $filesanddirs = $fs->get_area_files($contextid, 'local_assignbulk', 'submissions', $userid);
        foreach ($filesanddirs as $dir) {
            if ($dir->is_directory() && !array_key_exists($dir->get_filepath(), $inuse)) {
                // Not in use, delete!
                $dir->delete();
            }
        }
    }

    /**
     * Submissions can be regular files or directories; we compare the name of the directory or the name of the file without
     * extension to the user identifier to determine whether or not this is a valid submission file.
     * @param  stored_file $file [description]
     * @return [type]            [description]
     */
    protected function effective_file_name(stored_file $file) {
        $filename = $file->is_directory() ? basename($file->get_filepath()) : $file->get_filename();
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * Cache of user identifiers
     * @var string => stdClass  User identifier to user object
     */
    private $_useridents;

    /**
     * Return the user for this identifier
     * @param  string  $identifier The user identifier (i.e. $user->$ident)
     * @param  boolean $throw      If true, throws if no user is found by this name, otherwise return null
     * @return stdClass|null       The user object, or null if the user wasn't found
     */
    public function user_for_ident($identifier, $throw = true) {
        if (!isset($this->_useridents)) {
            $this->_init_useridents();
        }

        if (isset($this->_useridents[$identifier])) {
            return $this->_useridents[$identifier];
        } else {
            if ($throw) {
                throw new moodle_exception('invaliduser', 'local_assignbulk', $this->errorurl, $identifier);
            }
        }

        return null;
    }

    /**
     * Initialise the user identifier cache
     */
    private function _init_useridents() {
        $ident = $this->ident;
        $useridents = [];
        $participants = $this->assign->list_participants(0, false);

        foreach ($participants as $user) {
            if (empty($user->$ident)) {
                continue;
            }
            if (isset($useridents[$user->$ident])) {
                $a = ['ident' => $ident, 'value' => $user->$ident];
                throw new moodle_exception('identnotunique', 'local_assignbulk', $this->errorurl, $a);
            }
            $useridents[$user->$ident] = $user;
        }

        $this->_useridents = $useridents;
    }

    /**
     * Move the file from the staging area to the user-specific submission area.
     * As much as this function is called "copy", it deletes the file from the original area; it's more of a move...
     * @param  stored_file $file The file to move
     * @param  stdClass    $user The user object to move the file to. Only $id is actually needed, though.
     */
    private function copy_to_userdir(stored_file $file, $user) {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;
        $s = $this->staging;

        $userdir = new stdClass;
        $userdir->contextid = $contextid;
        $userdir->itemid = $user->id;
        $userdir->component = 'local_assignbulk';
        $userdir->filearea = 'submissions';

        if ($file->is_directory()) {
            // Copy every file in the directory.
            $subfiles = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], $file->get_filepath(), true, true);
        } else {
            $subfiles = [$file];
        }

        foreach ($subfiles as $file) {
            if ($file->is_directory()) {
                // Handle rawImages in a multi-file directory based upload.
                if ($filepath = $this->is_raw_images($file)) {
                    $this->rawimages[$filepath] = $file;
                }
            } else {
                if ($this->is_raw_images($file)) {
                    continue;
                }

                // Delete the old file if it exists from a previous failed upload.
                $oldfile = $fs->get_file($userdir->contextid,
                                         $userdir->component,
                                         $userdir->filearea,
                                         $userdir->itemid,
                                         $file->get_filepath(),
                                         $file->get_filename());
                if (!empty($oldfile)) {
                    $oldfile->delete();
                }
                $this->filepaths[$file->get_contenthash()] = $file->get_filepath() . $file->get_filename();
                $fs->create_file_from_storedfile($userdir, $file);
                // Delete the stored file in the staging area.
                $file->delete();
            }
        }
    }

    /**
     * Add the files as a user submission to this assignment.
     * This works by creating a submission form, extracting the form data by way of a public method to access the private data,
     * then submitting that form to the assignment. From the assignment's perspective, this is identical to the user themselves
     * creating a submission using the normal web interface.
     *
     * TODO: This doesn't really use the passed in $files parameter, instead relying on the construction of the user's
     * submission file area. Should we get rid of the param, or start using it?
     *
     * @uses \local_assignbulk\upload_form
     * @param  stored_file[]  $files  The files to submit on behalf of the user
     * @param  stdClass       $user   The user to submit on behalf of
     * @param  boolean        $delete Unused, for now
     * @return array          A single entry in the users feedback described in {@link bulk_uploader::execute()}
     */
    protected function push_files_to_assign($files, $user, $delete = false) {
        global $DB;

        $contextid = $this->assign->get_context()->id;

        $submissionplugin = $this->assign->get_submission_plugin_by_type('file');

        // Assignsubmission_file doesn't have a nice way to do this, so we fake a form submission.
        $userfb = ['fullname' => fullname($user), 'id' => $user->id];

        $formdata = new stdClass;
        $formdata->userid = $user->id;

        $mform = new submission_form(null, array($this->assign, $formdata));

        $formdata = $mform->export_values();

        // First create a draftarea with the files we want.
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $contextid, 'local_assignbulk', 'submissions', $user->id, ['subdirs' => true]);
        file_merge_draft_area_into_draft_area($draftitemid, $formdata->files_filemanager);

        $this->assign->save_submission((object)$formdata, $notices);

        // Add the raw images.
        $rawimages = false;
        if (!empty($this->rawimages)) {
            $editpdf = $this->assign->get_feedback_plugin_by_type('editpdf');
            if (!is_null($editpdf)) {
                $submission = $this->assign->get_user_submission($user->id, false, -1);
                $rawimages = $this->handle_raw_images($submission);
            }
        }

        if (!empty($notices)) {
            $userfb['notices'] = $notices;
        } else {
            $userfb['submissions'] = [];
            foreach ($files as $file) {
                $userfb['submissions'][] = ['filename' => $file->get_filepath() . $file->get_filename(),
                'rawImages' => $rawimages];
            }
        }

        return $userfb;
    }

    /**
     * Bad name for function, sort of... it returns the filepath + name associated with a rawImages directory
     * or false if this isn't a rawImages directory.
     * @param  stored_file $file A rawImages directory or a file within it
     * @return boolean|string    as above
     */
    private function is_raw_images(stored_file $file) {
        $pathinfo = pathinfo($file->get_filepath());
        if (array_key_exists('extension', $pathinfo) && ($pathinfo['extension'] == "rawImages")) {
            if ($pathinfo['dirname'] == '/') {
                return '/' . $pathinfo['filename'];
            } else {
                return $pathinfo['dirname'] . '/' . $pathinfo['filename'];
            }
        }
        return false;
    }

    /**
     * Adds the uploaded rendered pages to the assignment's cache of pre-rendered pages
     * @param  stdClass $submission A submission in the assignment
     */
    private function handle_raw_images($submission) {
        $pagenumber = 0;
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $document = document_services::get_combined_pdf_for_attempt($this->assign, $submission->userid, -1);

        $record = new \stdClass();
        $record->contextid = $contextid;
        $record->component = 'assignfeedback_editpdf';
        $record->filearea = document_services::PAGE_IMAGE_FILEAREA;
        $record->itemid = $document->get_combined_file()->get_itemid();
        $record->filepath = '/';
        $record->timemodified = $submission->timemodified;

        // We can only handle combined documents that are ready to go now.
        // Frankly, conversions can't properly be supported at all, but if
        // they occurred instantly then maybe they're a one-to-one conversion?

        if ($document->get_status() !== combined_document::STATUS_COMPLETE) {
            return false;
        }

        foreach ($document->get_source_files() as $file) {
            if ($file instanceof conversion) {
                $sourcefile = $file->get_sourcefile();
                $destfile = $file->get_destfile();
            } else if ($file instanceof stored_file) {
                $sourcefile = $file;
                $destfile = $file;
            }

            $filename = $this->filepaths[$sourcefile->get_contenthash()];
            if (isset($this->rawimages[$filename])) {
                $rawdir = $this->rawimages[$filename];
                $compatiblepdf = pdf::ensure_pdf_compatible($destfile);
                if ($compatiblepdf) {
                    $pdf = new pdf();
                    $numpages = $pdf->load_pdf($compatiblepdf);
                    for ($i = 0; $i < $numpages; $i++) {
                        $rawfilename = "image_page$i.png";
                        $rawfile = $fs->get_file(
                            $rawdir->get_contextid(),
                            $rawdir->get_component(),
                            $rawdir->get_filearea(),
                            $rawdir->get_itemid(),
                            $rawdir->get_filepath(),
                            $rawfilename
                        );

                        if ($rawfile) {
                            $record->filename = 'image_page' . ($pagenumber + $i) . '.png';
                            $oldfile = $fs->get_file(
                                $record->contextid,
                                $record->component,
                                $record->filearea,
                                $record->itemid,
                                $record->filepath,
                                $record->filename);
                            if ($oldfile) {
                                $oldfile->delete();
                            }
                            $newfile = $fs->create_file_from_storedfile($record, $rawfile);

                            // Add the readonly version.
                            $oldfile = $fs->get_file(
                                $record->contextid,
                                $record->component,
                                document_services::PAGE_IMAGE_READONLY_FILEAREA,
                                $record->itemid,
                                $record->filepath,
                                $record->filename);
                            if ($oldfile) {
                                $oldfile->delete();
                            }
                            $fs->create_file_from_storedfile(
                                ['filearea' => document_services::PAGE_IMAGE_READONLY_FILEAREA],
                                $newfile
                            );
                        }
                    }
                    $pagenumber += $numpages;
                }
            }
        }

        return true;
    }

    /**
     * Convenience function to prepare a draft area for uploading submission files to.
     * @return int  The draft item id that should be passed to the upload form and also to {@link bulk_uploader::execute()}
     */
    public function prepare_draft_area() {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $draftitemid = file_get_submitted_draft_itemid('submissions');
        if (empty($draftitemid)) {
            $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        }

        file_prepare_draft_area($draftitemid, $contextid, 'local_assignbulk', 'staging', null);

        return $draftitemid;
    }

}