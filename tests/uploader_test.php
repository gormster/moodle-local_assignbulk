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
 * @package     local_assignbulk\tests
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use local_assignbulk\bulk_uploader;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

class local_assignbulk_uploader_testcase extends advanced_testcase {

    protected $assign;

    protected $course;

    protected $students;

    protected $teacher;

    public function setUp() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assign = $generator->create_instance(array('course' => $this->course->id, 'teamsubmission' => true));
        $context = context_module::instance($assign->cmid);
        $this->assign = new assign($context, null, $this->course);

        // Make some users & some groups.
        for ($i = 0; $i < 20; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->students[$user->id] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        }

        // Make a teacher role.
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    public function test_user_for_ident_1() {
        $uploader = new bulk_uploader($this->assign, 'username');

        $usernames = [];
        foreach ($this->students as $user) {
            $usernames[] = $user->username;
        }

        $users = array_map([$uploader, 'user_for_ident'], $usernames);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    public function test_user_for_ident_2() {
        $uploader = new bulk_uploader($this->assign, 'email');

        $emails = [];
        foreach ($this->students as $user) {
            $emails[] = $user->email;
        }

        $users = array_map([$uploader, 'user_for_ident'], $emails);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    public function test_user_for_ident_3() {
        global $DB;

        $uploader = new bulk_uploader($this->assign, 'idnumber');

        $idnumbers = [];
        foreach ($this->students as $user) {
            $user->idnumber = "{$user->firstname}{$user->id}";
            $DB->update_record('user', $user);
            $idnumbers[] = $user->idnumber;
        }

        $users = array_map([$uploader, 'user_for_ident'], $idnumbers);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    /**
     * @expectedException moodle_exception
     * @expectedExceptionMessage not unique
     */
    public function test_user_for_ident_4() {
        global $DB;

        $uploader = new bulk_uploader($this->assign, 'idnumber');

        $idnumbers = [];
        foreach ($this->students as $user) {
            $user->idnumber = $user->id % 10;
            $DB->update_record('user', $user);
            $idnumbers[] = $user->idnumber;
        }

        $users = array_map([$uploader, 'user_for_ident'], $idnumbers);
    }

}