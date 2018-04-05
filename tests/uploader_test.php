<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

use local_assignbulk\bulk_uploader;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

class local_assignbulk_uploader_testcase extends advanced_testcase {

    protected $assign;

    protected $course;

    protected $students;

    protected $teacher;

    function setUp() {
        $this->resetAfterTest();

        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assign = $generator->create_instance(array('course'=>$this->course->id, 'teamsubmission' => true));
        $context = context_module::instance($assign->cmid);
        $this->assign = new assign($context, null, $this->course);

        // make some users & some groups
        for($i = 0; $i < 20; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->students[$user->id] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        }

        // make a teacher role
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    // function test_folder_name_1() {
    //     $uploader = new bulk_uploader($this->assign, 'username');

    //     $names = array_map([$uploader, 'folder_name'], array_keys($this->students));

    //     $this->assertEquals(count($this->students), count($names), "Should be exactly one folder per user");

    //     $duplicates = array_diff(array_count_values($names), [1]);
    //     $this->assertEmpty($duplicates, "Some names appear more than once: " . print_r($duplicates, true));
    // }

    // function test_folder_name_2() {

    //     // Add a second student with the same name
    //     $eight = array_values($this->students)[8];
    //     $new = new stdClass;
    //     $new->firstname = $eight->firstname;
    //     $new->lastname = $eight->lastname;

    //     $newuser = $this->getDataGenerator()->create_user($new);
    //     $this->students[$newuser->id] = $newuser;
    //     $this->getDataGenerator()->enrol_user($newuser->id, $this->course->id);

    //     // Perform test
    //     $this->test_folder_name_1();
    // }

    // function test_folder_name_3() {
    //     global $DB;

    //     foreach ($this->students as $user) {
    //         $user->firstname = "Borg";
    //         $user->lastname = "Borgensson";
    //         $DB->update_record('user', $user);
    //     }

    //     $this->test_folder_name_1();
    // }

    // /**
    //  * @expectedException moodle_exception
    //  * @expectedExceptionMessage not a submitter
    //  */
    // function test_folder_name_4() {
    //     // Add a user who isn't enrolled in this course
    //     $newuser = $this->getDataGenerator()->create_user();
    //     $this->students[$newuser->id] = $newuser;

    //     $this->test_folder_name_1();
    // }

    function test_user_for_ident_1() {
        $uploader = new bulk_uploader($this->assign, 'username');

        $usernames = [];
        foreach ($this->students as $user) {
            $usernames[] = $user->username;
        }

        $users = array_map([$uploader, 'user_for_ident'], $usernames);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    function test_user_for_ident_2() {
        $uploader = new bulk_uploader($this->assign, 'email');

        $emails = [];
        foreach ($this->students as $user) {
            $emails[] = $user->email;
        }

        $users = array_map([$uploader, 'user_for_ident'], $emails);

        $this->assertEquals(array_values($this->students), array_values($users), "Some users did not match up again");
    }

    function test_user_for_ident_3() {
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
    function test_user_for_ident_4() {
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