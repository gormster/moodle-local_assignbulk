<?php

namespace local_assignbulk;

defined('MOODLE_INTERNAL') || die;

use assign;

class upload_form extends \moodleform {

    public function definition() {
        global $CFG;

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('filemanager', 'submissions', get_string('file', 'assignsubmission_file')); // Add elements to your form
        $mform->addRule('submissions', null, 'required');

        $options = [
            'username' => get_string('username'),
            'idnumber' => get_string('idnumber'),
            'email' => get_string('email'),
        ];
        $mform->addElement('select', 'identifier', get_string('identifier', 'local_assignbulk'), $options);

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'previewbutton', get_string('preview'));
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

}