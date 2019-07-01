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
 * Basic uploader testing traits
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use local_assignbulk\bulk_uploader;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Useful tools for tests. This isn't a subclass of advanced_testcase because that would cause the tester to complain there
 * are no tests in it, and we don't want to waste time performing unnecessary tests.
 */
trait local_assignbulk_basic_test {

    protected $assign;

    protected $course;

    protected $students;

    protected $teacher;

    /**
     * Set up the test environment
     * @see \PHPUnit\Framework\TestCase::setUp
     */
    protected function setUp() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assign = $generator->create_instance(array(
            'course' => $this->course->id,
            'assignsubmission_file_enabled' => true,
            'assignsubmission_file_maxfiles' => 10,
            'assignsubmission_file_maxsizebytes' => 1024
        ));
        $context = context_module::instance($assign->cmid);
        $this->assign = new assign($context, null, $this->course);

        // Make some users & some groups.
        for ($i = 0; $i < 20; $i++) {
            $user = $this->getDataGenerator()->create_user([
                'username' => sprintf('user%02u', $i + 1),
                'idnumber' => sprintf('%03u', $i + 1)
            ]);
            $this->students[$user->id] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        }

        // Make a teacher role.
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    /**
     * Create a draft area with the contents of this folder from the tests/fixtures directory
     * You must call this as the same user you will be testing as; draft areas are scoped to the user
     * @param  string $fixture  The name of the fixture directory
     * @return int              A draft item ID for the draft area
     */
    protected function prepare_fixture($fixture) {
        $uploader = new bulk_uploader($this->assign, 'username');
        $draftitemid = $uploader->prepare_draft_area();
        $this->install_fixture($fixture, $draftitemid);
        return $draftitemid;
    }

    /**
     * Copy files from the given fixture folder to the given draft area
     * @see local_assignbulk_basic_test::prepare_fixture()
     * @param  string $fixture     The name of the fixture directory
     * @param  int    $draftitemid A draft item ID for the draft area (already created)
     */
    protected function install_fixture($fixture, $draftitemid) {
        global $USER;

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();

        $record = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            ];

        $dir = dirname(__FILE__) . '/fixtures/' . $fixture;
        $dirs = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dirs);
        foreach ($files as $file) {
            if ($file->isFile()) {
                $dirname = $files->getInnerIterator()->getSubPath();
                $dirname = empty($dirname) ? '/' : '/' . $dirname . '/';
                $record['filepath'] = $dirname;
                $record['filename'] = $file->getFilename();
                $fs->create_file_from_pathname($record, $file->getPathname());
            }
        }
    }

}