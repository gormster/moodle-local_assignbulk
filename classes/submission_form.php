<?php

namespace local_assignbulk;

require_once($CFG->dirroot . '/mod/assign/submission_form.php');

class submission_form extends \mod_assign_submission_form {

    function export_values() {
        return (object)$this->_form->exportValues();
    }

}