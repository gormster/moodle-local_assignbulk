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
 * Subclass of mod_assign_submission_form that allows us to extract the form data
 *
 * This is necessary because we need to extract the subplugin variables
 * from the form, and they are normally inaccessible.
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignbulk;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/assign/submission_form.php');

/**
 * Subclass of mod_assign_submission_form that allows us to extract the form data
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends \mod_assign_submission_form {

    /**
     * Return the form data
     * @see MoodleQuickForm::exportValues()
     * @return array
     */
    public function export_values() {
        return (object)$this->_form->exportValues();
    }

    public function get_errors() {
        return $this->_form->_errors;
    }

}