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
require_once('base.php');

/**
 * Test the uploader
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assignbulk_user_ident_testcase extends advanced_testcase {

    use local_assignbulk_basic_test;

    /**
     * Get the correct users for their usernames
     */
    public function test_user_for_ident_1() {
        $uploader = new bulk_uploader($this->assign, 'username');

        $usernames = [];
        foreach ($this->students as $user) {
            $usernames[] = $user->username;
        }

        $users = array_map([$uploader, 'user_for_ident'], $usernames);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    /**
     * Get the correct users for their emails
     */
    public function test_user_for_ident_2() {
        $uploader = new bulk_uploader($this->assign, 'email');

        $emails = [];
        foreach ($this->students as $user) {
            $emails[] = $user->email;
        }

        $users = array_map([$uploader, 'user_for_ident'], $emails);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    /**
     * Get the correct users for their idnumbers
     */
    public function test_user_for_ident_3() {
        global $DB;

        $uploader = new bulk_uploader($this->assign, 'idnumber');

        $idnumbers = [];
        foreach ($this->students as $user) {
            $idnumbers[] = $user->idnumber;
        }

        $users = array_map([$uploader, 'user_for_ident'], $idnumbers);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    /**
     * Fail on non-unique idnumbers
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

    /**
     * Fail on non-existent username
     * @expectedException moodle_exception
     * @expectedExceptionMessage not a submitter
     */
    public function test_user_for_ident_5() {
        global $DB;

        $uploader = new bulk_uploader($this->assign, 'username');

        $uploader->user_for_ident('notauser');
    }

}