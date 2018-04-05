<?php

namespace local_assignbulk;

use moodle_exception;
use coding_exception;
use assign;
use context_user;
use stored_file;
use stdClass;
use moodle_url;

class bulk_uploader {

    private $assign;

    private $ident;

    private $staging;

    private $error_url;

    private $commit;

    function __construct($assign, $ident) {
        $this->assign = $assign;
        $this->ident = $ident;
        $id = $assign->get_course_module()->id;
        $this->error_url = new moodle_url('local/assignbulk/upload.php', ['id' => $id]);
    }

    function execute($draftitemid, $commit = true) {

        $feedback = new stdClass();

        $this->commit = $commit;

        // 1. Copy files to staging area
        $this->save_draft_files($draftitemid);

        // 2. Unzip any top level compressed files that don't match the naming scheme
        $this->unzip_top_level_files();

        // 3. Walk the tree
        $this->walk_step('/');

        // 4. Submit the files for the users
        $feedback->users = $this->submit_user_files();

        // 5. Make a note of the remaining non-directory contents of the staging area to report to the user
        $feedback->warnings = $this->remaining_unstaged_files();

        // 6. Delete the contents of the staging area
        if (empty($feedback->warnings)) {
            $this->delete_staging_area();
        }

        return $feedback;

    }

    function save_draft_files($draftitemid, $delete = false) {
        global $USER;

        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        // Clear the area first
        $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        $fs->delete_area_files($contextid, 'local_assignbulk', 'submissions');

        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id');

        $change = new stdClass();
        $change->contextid = $contextid;
        $change->component = 'local_assignbulk';
        $change->filearea = 'staging';
        foreach ($files as $file) {
            $fs->create_file_from_storedfile($change, $file);
        }

        $this->staging = [$change->contextid, $change->component, $change->filearea, $draftitemid];
    }

    function unzip_top_level_files() {
        $fs = get_file_storage();
        $s = $this->staging;
        $topfiles = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], '/', false, false);
        foreach ($topfiles as $file) {
            // If the item is not a compressed file, continue
            $mimetype = $file->get_mimetype();
            $packer = get_file_packer($mimetype);
            if (empty($packer)) {
                continue;
            }

            // If the item matches the naming scheme, continue
            $filename = $this->effective_file_name($file);
            if (!empty($this->user_for_ident($filename, false))) {
                continue;
            }

            // Unzip the item in place
            $pathbase = '/' . $file->get_filename() . '/';
            $fs->create_directory($s[0], $s[1], $s[2], $s[3], $pathbase);
            $results = $file->extract_to_storage($packer, $s[0], $s[1], $s[2], $s[3], $pathbase);

            // If unzipping the file causes any overwritten files, throw an exception
            foreach($results as $filename => $result) {
                if ($result != true) {
                    throw new moodle_exception('local_assignbulk', 'unpackerror', $this->error_url, $result);
                }
            }

            // Delete the zip file so it doesn't show up as a leftover when
            // checking the remaining contents of the staging area
            $file->delete();
        }
    }

    function walk_step($directory) {
        $fs = get_file_storage();
        $s = $this->staging;
        $files = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], $directory, false, true);

        $subpaths = [];

        foreach ($files as $file) {
            $filename = $this->effective_file_name($file);
            $user = $this->user_for_ident($filename, false);
            if (!empty($user)) {
                $this->copy_to_userdir($file, $user);
                continue;
            }

            if ($file->is_directory()) {
                $subpaths[] = $file->get_filepath();
            }
        }

        foreach ($subpaths as $subpath) {
            $this->walk_step($subpath);
        }
    }

    function submit_user_files() {
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

        // Create a reverse lookup array for idents
        $useridentsreverse = [];
        foreach ($this->_useridents as $userident => $user) {
            $useridentsreverse[$user->id] = $userident;
        }

        $feedback = [];
        foreach ($submissions as $userid => $files) {
            $userident = $useridentsreverse[$userid];
            $user = $this->user_for_ident($userident);
            $this->simplify_user_paths($files, $userident);
            $this->delete_empty_directories($files, $userid);
            if ($this->commit) {
                $feedback[] = $this->push_files_to_assign($files, $user);
            } else {
                // Mock up preview feedback
                $feedback[] = ['fullname' => fullname($user), 'submissions' => array_map(function($v) { return ['filename' => $v->get_filename()]; }, $files)];
            }
        }

        return $feedback;

    }

    function remaining_unstaged_files() {
        $fs = get_file_storage();
        $s = $this->staging;

        $staging = $fs->get_area_files($s[0], $s[1], $s[2], $s[3]);
        $remaining = [];
        foreach($staging as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $remaining[] = $file->get_filepath() . $file->get_filename();
        }

        return $remaining;
    }

    function delete_staging_area() {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;
        $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        $fs->delete_area_files($contextid, 'local_assignbulk', 'submissions');
    }

    function simplify_user_paths($files, $userident) {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        // 1. Find the longest common prefix
        $prefix = explode('/', trim($files[0]->get_filepath(), '/'));
        $pathlen = count($prefix);
        $samename = true; // this is for step 2

        foreach ($files as $file) {
            $path = explode('/', trim($file->get_filepath(), '/'));
            $prefix = array_intersect_assoc($prefix, $path);
            if (empty($prefix)) {
                break; // No point continuing
            }
            if ($samename &&
                (($this->effective_file_name($file) != $userident) ||
                (count($path) != $pathlen))) {
                $samename = false;
            }
        }

        if (!empty($prefix)) {
            $dropstr = '/' . implode('/', $prefix);
            $droplen = strlen($dropstr);
            foreach ($files as $file) {
                if (substr($file->get_filepath(), 0, $droplen) != $dropstr) {
                    throw new coding_exception('Assertion failure: file path does not begin with prefix string');
                }
                $newpath = substr($file->get_filepath(), $droplen);
                $file->rename($newpath, $file->get_filename());
            }

            $pathlen -= count($prefix);
        }

        // 2. If the files are all named the same thing, rename them to their directory's name
        if (count($files) > 1 && $samename) {
            // We actually have to quickly iterate to see if there is some path component that differs for all the files
            // Otherwise we'll end up with name clashes
            $pathcomponents = array_fill(0, $pathlen, []);
            foreach ($files as $file) {
                $path = explode('/', trim($file->get_filepath(), '/'));
                if (count($path) != $pathlen) {
                    throw new coding_exception('Assertion failure: paths should all be the same length by this point');
                }
                for ($i=0; $i < $pathlen; $i++) {
                    $pathcomponents[$i][] = $path[$i];
                }
            }

            $varindex = -1;
            $commonsuffix = null;
            foreach ($pathcomponents as $i => $comps) {
                if(count(array_unique($comps)) == count($files)) {
                    // There is a unique component at this index for ALL files
                    $varindex = $i;
                    $commonsuffix = true;
                } else if (($varindex > -1) && (count(array_unique($comps)) > 1)) {
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

    function delete_empty_directories($files, $userid) {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $inuse = [];
        foreach ($files as $file) {
            $path = explode('/', rtrim($file->get_filepath(), '/'));
            while(count($path)) {
                $p = implode('/', $path) . '/';
                $inuse[$p] = true;
                array_pop($path);
            }
        }

        $files_and_dirs = $fs->get_area_files($contextid, 'local_assignbulk', 'submissions', $userid);
        foreach($files_and_dirs as $dir) {
            if ($dir->is_directory() && !array_key_exists($dir->get_filepath(), $inuse)) {
                // not in use, delete
                $dir->delete();
            }
        }
    }

    function effective_file_name(stored_file $file) {
        if ($file->is_directory()) {
            return basename($file->get_filepath());
        } else {
            return pathinfo($file->get_filename(), PATHINFO_FILENAME);
        }
    }

    private $_useridents;
    function user_for_ident($identifier, $throw = true) {
        if (!isset($this->_useridents)) {
            $this->_init_useridents();
        }

        if (isset($this->_useridents[$identifier])) {
            return $this->_useridents[$identifier];
        } else {
            if ($throw) {
                throw new moodle_exception('invaliduser', 'local_assignbulk', null, $identifier);
            }
        }

        return null;
    }

    private function _init_useridents() {
        $ident = $this->ident;
        $useridents = [];
        $participants = $this->assign->list_participants(0, false);

        foreach ($participants as $user) {
            if (isset($useridents[$user->$ident])) {
                throw new moodle_exception('identnotunique', 'local_assignbulk', null, ['ident' => $ident, 'value' => $user->$ident]);
            }
            $useridents[$user->$ident] = $user;
        }

        $this->_useridents = $useridents;
    }

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
            // copy every file
            $subfiles = $fs->get_directory_files($s[0], $s[1], $s[2], $s[3], $file->get_filepath(), true, false);
        } else {
            $subfiles = [$file];
        }

        foreach ($subfiles as $file) {
            // Delete the old file if it exists from a previous failed upload
            $oldfile = $fs->get_file($userdir->contextid, $userdir->component, $userdir->filearea, $userdir->itemid, $file->get_filepath(), $file->get_filename());
            if (!empty($oldfile)) {
                $oldfile->delete();
            }
            $fs->create_file_from_storedfile($userdir, $file);
            // Delete the stored file in the staging area
            $file->delete();
        }
    }

    function push_files_to_assign($files, $user, $delete = false) {
        global $DB;

        $contextid = $this->assign->get_context()->id;

        $submissionplugin = $this->assign->get_submission_plugin_by_type('file');

        // assignsubmission_file doesn't have a nice way to do this, so we fake a form submission
        $userfb = ['fullname' => fullname($user)];

        $formdata = new stdClass;
        $formdata->userid = $user->id;

        $mform = new submission_form(null, array($this->assign, $formdata));
        $formdata = $mform->export_values();

        // first create a draftarea with the files we want
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $contextid, 'local_assignbulk', 'submissions', $user->id, ['subdirs' => true]);
        file_merge_draft_area_into_draft_area($draftitemid, $formdata->files_filemanager);

        $this->assign->save_submission((object)$formdata, $notices);
        if (!empty($notices)) {
            $userfb['notices'] = $notices;
        } else {
            $userfb['submissions'] = [];
            foreach ($files as $file) {
                $userfb['submissions'][] = ['filename' => $file->get_filename()];
            }
        }

       return $userfb;
    }

    function prepare_draft_area() {
        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;

        $draftitemid = file_get_submitted_draft_itemid('submissions');
        // If we decide to reimplement the bidirectional file thing
        // file_prepare_draft_area($draftitemid, $context->id, 'local_assignbulk', 'submissions', $cm->id, ['subdirs' => true]);
        if (empty($draftitemid)) {
            $fs->delete_area_files($contextid, 'local_assignbulk', 'staging');
        }

        file_prepare_draft_area($draftitemid, $contextid, 'local_assignbulk', 'staging', null);

        return $draftitemid;
    }

}