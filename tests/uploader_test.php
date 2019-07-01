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
 * Test the uploader
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_assignbulk\bulk_uploader;
use assignfeedback_editpdf\document_services;

require_once('base.php');

/**
 * Test the uploader
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assignbulk_uploader_testcase extends advanced_testcase {

    use local_assignbulk_basic_test;

    /**
     * Tests various file configurations
     * @param  string $fixture       The name of the fixture
     * @param  string $ident         The user identifier field
     * @param  array  $expectedfiles [$username => [$filepath . $filename]]
     * @dataProvider top_level_files
     * @dataProvider deep_level_files
     * @dataProvider multiple_files
     * @dataProvider edge_cases
     */
    public function test_execute($fixture, $ident, $expectedfiles) {
        $draftitemid = $this->prepare_fixture($fixture);

        $uploader = new bulk_uploader($this->assign, $ident);

        $uploader->execute($draftitemid);

        $plugin = $this->assign->get_submission_plugin_by_type('file');
        foreach ($this->students as $userid => $user) {
            $expected = $expectedfiles[$user->username];
            $submission = $this->assign->get_user_submission($userid, false);
            if ($expected === false) {
                $this->assertFalse($submission);
                continue;
            }

            $files = $plugin->get_files($submission, $user);
            $this->assertEquals($expected, array_keys($files), 'File arrays were not equal', 0, 10, true);
        }
    }

    /**
     * Tests the preview feature
     * @param  string $fixture       The name of the fixture
     * @param  string $ident         The user identifier field
     * @param  array  $expectedfiles [$username => [$filepath . $filename]]
     * @dataProvider top_level_files
     * @dataProvider deep_level_files
     * @dataProvider multiple_files
     * @dataProvider edge_cases
     */
    public function test_preflight($fixture, $ident, $expectedfiles) {
        $draftitemid = $this->prepare_fixture($fixture);

        $uploader = new bulk_uploader($this->assign, $ident);

        $feedback = $uploader->execute($draftitemid, false);

        $userfeedback = array_column($feedback->users, null, 'id');

        foreach ($this->students as $userid => $user) {
            // If we don't have an expectation, we don't care.
            if (!isset($expectedfiles[$user->username])) {
                continue;
            }

            $expected = $expectedfiles[$user->username];
            if ($expected === false) {
                $this->assertArrayNotHasKey($userid, $userfeedback);
                continue;
            }

            $fb = $userfeedback[$userid];
            $this->assertEquals($expected, array_column($fb['submissions'], 'filename'), 'File arrays were not equal', 0, 10, true);
        }
    }

    /**
     * Tests the rawImages upload feature
     * @param  string $fixture       The name of the fixture
     * @param  string $ident         The user identifier field
     * @param  array  $pagehashes    [$username => [$pagecontenthash]]
     * @dataProvider rawimages_files
     */
    public function test_raw_images($fixture, $ident, $pagehashes) {
        $draftitemid = $this->prepare_fixture($fixture);

        $uploader = new bulk_uploader($this->assign, $ident);

        $uploader->execute($draftitemid);

        $fs = get_file_storage();
        $contextid = $this->assign->get_context()->id;
        $plugin = $this->assign->get_submission_plugin_by_type('file');
        foreach ($this->students as $userid => $user) {
            $expected = $pagehashes[$user->username];
            $grade = $this->assign->get_user_grade($userid, true);

            // editpdf doesn't give us a good way to check if page files have been rendered, so just check they exist.
            $pagefiles = $fs->get_area_files(
                $contextid,
                'assignfeedback_editpdf',
                document_services::PAGE_IMAGE_FILEAREA,
                $grade->id
            );

            if (empty($expected)) {
                $this->assertEmpty($pagefiles);
                continue;
            }

            $pages = [];
            foreach ($pagefiles as $pagefile) {
                $pages[$pagefile->get_filename()] = $pagefile->get_contenthash();
            }

            foreach ($expected as $pagenum => $contenthash) {
                $filename = 'image_page' . $pagenum . '.png';
                $this->assertEquals($contenthash, $pages[$filename]);
            }
        }
    }

    public function rawimages_files() {
        $contenthashes = [
            'user01' => ['d975a1241637f369ef870f734b6bff8d56749bb1'],
            'user02' => ['7dfdfd04963bef07834039ebc77cfe6a87075edc'],
            'user03' => ['216a6c75c0646108b73af8555deb330035dbbf11'],
            'user04' => ['ff3afdf4fe63c4739df071c269f464a770697a3b'],
            'user05' => ['aef1689fec1ecadd341c5895b134ac341c8123c8'],
            'user06' => ['3371562cf1ab5a04454fb938a86c5990e5299ceb'],
            'user07' => ['faa76e605cb4fa65372dd4307df7bc076878b7f8'],
            'user08' => ['747acf1ea574b8f0e7770579a4ebe05db719dbac'],
            'user09' => ['185d491ac8bf8fb6039e25305d4c3b37262e221b'],
            'user10' => ['5d2bc8448a87b0ed18378c4e83c967d536f20265'],
            'user11' => ['4074349162a54a7b4cd2638b1113b87729bfe6af'],
            'user12' => ['d2a86bb348b6f9a0e1b218bc98593421029a7989'],
            'user13' => ['ee58c0cadec5ad4f6ca55aca760cb11e781a5906'],
            'user14' => ['c8febcf4a907da13eb4e069284171ecf6911f3de'],
            'user15' => ['ce90403ea2f3db8b5b58ef94e3e71b7792b33b98'],
            'user16' => ['bc665ea26740038740ddbae143084de737a73de8'],
            'user17' => ['9adbe27c7d1242a40edccdaff608c44bbb660fea'],
            'user18' => ['fd693de764c2499f7cf6f3801df55d42e7ac34e2'],
            'user19' => ['01ab35d1f145e173f56d6f69182da5c2acb84463'],
            'user20' => ['730b8602e9864e09fc505ab490e99a39d0d68fdc']
        ];

        return array_column([
            ['rawimages_top_level_files', 'username', $contenthashes],
            ['rawimages_folder_per_user', 'username', $contenthashes]
        ], null, 0);
    }

    /**
     * Non-nested file tests
     * @see local_assignbulk_uploader_testcase::test_execute
     * @return array
     */
    public function top_level_files() {
        $expectedfiles = [
            'user01' => ['/user01.txt'],
            'user02' => ['/user02.txt'],
            'user03' => ['/user03.txt'],
            'user04' => ['/user04.txt'],
            'user05' => ['/user05.txt'],
            'user06' => ['/user06.txt'],
            'user07' => ['/user07.txt'],
            'user08' => ['/user08.txt'],
            'user09' => ['/user09.txt'],
            'user10' => ['/user10.txt'],
            'user11' => ['/user11.txt'],
            'user12' => ['/user12.txt'],
            'user13' => ['/user13.txt'],
            'user14' => ['/user14.txt'],
            'user15' => ['/user15.txt'],
            'user16' => ['/user16.txt'],
            'user17' => ['/user17.txt'],
            'user18' => ['/user18.txt'],
            'user19' => ['/user19.txt'],
            'user20' => ['/user20.txt'],
        ];

        // Set even numbered users to false
        $somemissing = array_merge($expectedfiles, array_fill_keys(['user02', 'user04', 'user06', 'user08', 'user10', 'user12', 'user14', 'user16', 'user18', 'user20'], false));

        return array_column([
            ['top_level_files', 'username', $expectedfiles],
            ['top_level_zip', 'username', $expectedfiles],
            ['top_level_missing', 'username', $somemissing],
            ['top_level_missing_zip', 'username', $somemissing],
            ['top_level_mixed', 'username', $expectedfiles],
            ['top_level_multizip', 'username', $expectedfiles],
            ['zipped_top_level_files', 'username', $expectedfiles],
        ], null, 0);
    }

    /**
     * Nested file tests
     * @see local_assignbulk_uploader_testcase::test_execute
     * @return array
     */
    public function deep_level_files() {
        $expectedfiles = [
            'user01' => ['/submission.txt'],
            'user02' => ['/submission.txt'],
            'user03' => ['/submission.txt'],
            'user04' => ['/submission.txt'],
            'user05' => ['/submission.txt'],
            'user06' => ['/submission.txt'],
            'user07' => ['/submission.txt'],
            'user08' => ['/submission.txt'],
            'user09' => ['/submission.txt'],
            'user10' => ['/submission.txt'],
            'user11' => ['/submission.txt'],
            'user12' => ['/submission.txt'],
            'user13' => ['/submission.txt'],
            'user14' => ['/submission.txt'],
            'user15' => ['/submission.txt'],
            'user16' => ['/submission.txt'],
            'user17' => ['/submission.txt'],
            'user18' => ['/submission.txt'],
            'user19' => ['/submission.txt'],
            'user20' => ['/submission.txt'],
        ];

        return array_column([
            ['deep_folder_per_user', 'username', $expectedfiles],
            ['zipped_deep_folder_per_user', 'username', $expectedfiles],
        ], null, 0);
    }

    /**
     * File tests with multiple files per submission
     * @see local_assignbulk_uploader_testcase::test_execute
     * @return array
     */
    public function multiple_files() {
        $usernames = [
            'user01',
            'user02',
            'user03',
            'user04',
            'user05',
            'user06',
            'user07',
            'user08',
            'user09',
            'user10',
            'user11',
            'user12',
            'user13',
            'user14',
            'user15',
            'user16',
            'user17',
            'user18',
            'user19',
            'user20'
        ];

        $expectedfiles = array_fill_keys($usernames, ['/question1.txt', '/question2.txt', '/question3.txt']);

        return array_column([
            ['multiple_one_per_folder', 'username', $expectedfiles],
            ['multiple_folder_per_user', 'username', $expectedfiles],
            ['multiple_deep', 'username', $expectedfiles],
            ['zipped_multiple_one_per_folder', 'username', $expectedfiles],
            ['zipped_multiple_folder_per_user', 'username', $expectedfiles],
        ], null, 0);
    }

    /**
     * Miscellaneous edge case files - ambiguous names, etc
     * @see local_assignbulk_uploader_testcase::test_execute
     * @return array
     */
    public function edge_cases() {
        $usernames = [
            'user01',
            'user02',
            'user03',
            'user04',
            'user05',
            'user06',
            'user07',
            'user08',
            'user09',
            'user10',
            'user11',
            'user12',
            'user13',
            'user14',
            'user15',
            'user16',
            'user17',
            'user18',
            'user19',
            'user20'
        ];

        // Zip files are not unpacked if they match a user identifier
        // This way cumbersome files can be assessed without worrying about if they'll be unzipped by the plugin
        $expectedzips = array_combine($usernames, array_map( function($v) { return ["/$v.zip"]; }, $usernames));

        // For the case where there are multiple files or folders that could potentially cause clashes in simplifying names,
        // we simply do nothing. The chance of losing valuable information is too high.
        $expectedmultifile = array_combine($usernames, array_map( function($v) { return [
            "/question1/$v/submission.txt",
            "/question2/$v/submission.txt",
            "/question3/$v/submission.txt",
            "/question3/$v/sbmn.txt"]; }, $usernames));

        // Even though this path could potentially be simplified, we leave it alone
        $expectedmultidir = array_combine($usernames, array_map( function($v) { return [
            "/question1/$v/response/submission.txt",
            "/question2/$v/response/submission.txt",
            "/question3/$v/response/submission.txt",
            "/question3/$v/rspn/submission.txt"]; }, $usernames));

        return array_column([
            ['edge_zip_per_user', 'username', $expectedzips],
            ['edge_multiple_filenames', 'username', $expectedmultifile],
            ['edge_multiple_folder_names', 'username', $expectedmultidir],
        ], null, 0);
    }

    /**
     * Test the file paths
     * @param  string  $ident      The user identifier
     * @param  string  $userident  The identifying mark for this user (i.e. $user->$ident)
     * @param  array   $files      Array of 2-tuples of input and expected filenames
     * @dataProvider user_paths_basic
     */
    public function test_simplify_user_paths($ident, $userident, $files) {
        $uploader = new bulk_uploader($this->assign, $ident);

        $fs = get_file_storage();

        $testfiles = [];
        $expectedresults = [];

        foreach ($files as list($file, $expected)) {
            list($filepath, $filename) = $this->splitname($file);

            $record = new stdClass();
            $record->contextid = $this->assign->get_context()->id;
            $record->component = 'local_assignbulk';
            $record->filearea = 'staging';
            $record->itemid = file_get_unused_draft_itemid();
            $record->filepath = $filepath;
            $record->filename = $filename;

            $storedfile = $fs->create_file_from_string($record, 'This is a test submission file ' . $file . ' for ' . $userident);
            $expectedresults[$storedfile->get_id()] = $expected;
            $testfiles[] = $storedfile;
        }

        phpunit_util::call_internal_method($uploader, 'simplify_user_paths', [$testfiles, $userident], bulk_uploader::class);

        foreach ($testfiles as $file) {
            list($expectedpath, $expectedname) = $this->splitname($expectedresults[$file->get_id()]);

            $this->assertEquals($expectedpath, $file->get_filepath());
            $this->assertEquals($expectedname, $file->get_filename());
        }
    }

    /**
     * Convenience function for splitting a path into filepath and filename
     * How is this not a moodle provided function?
     * @param  string $n   The full file path
     * @return string[]    ($filepath, $filename)
     */
    private function splitname($n) {
        $i = strrpos($n, '/') + 1;
        return [substr($n, 0, $i), substr($n, $i)];
    }

    /**
     * Some basic user path simplification tests
     * @see local_assignbulk_uploader_testcase::test_simplify_user_paths
     * @return array
     */
    public function user_paths_basic() {

        return [
            ['username', 'user01', [
                ['/user01.txt', '/user01.txt']
            ]],
            ['username', 'user01', [
                ['/submission.txt', '/submission.txt']
            ]],
            ['username', 'user01', [
                ['/user01/submission.txt', '/submission.txt']
            ]],
            ['username', 'user01', [
                ['/user01/submission.txt', '/submission.txt'],
                ['/user01/biblio.txt', '/biblio.txt']
            ]],
            ['username', 'user01', [
                ['/submission/user01.txt', '/submission.txt'],
                ['/biblio/user01.txt', '/biblio.txt']
            ]],
        ];

    }

    // TODO: Failing, warning & notice tests for upload and simplify user paths.

}
