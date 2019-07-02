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
 * Plugin strings are defined here.
 *
 * @package     local_assignbulk
 * @category    string
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Assignment Bulk Upload';
$string['bulkuploadsubmissions'] = 'Bulk upload submissions';
// TODO: this is terrible, fix it!
$string['uploadhelp'] = '
<p>Using this tool, you can upload assignment submissions as files. Note that this only works for the <strong>File</strong> submission type.</p>
<p>You can upload individual files, a ZIP containing the submission files, or multiple ZIP files containing multiple file submissions for each student. You should name each submission file with some way to identify the student, either with their username, ID number, or email address.</p>
<p>To submit multiple files per student, you can use folders.</p>
';
$string['uploadcomplete'] = '<p><strong>Upload complete.</strong> The following users have had their submission updated with these files:</p>';
$string['uploadpreview'] = '<p><strong>This is a preview.</strong> The following users will have their submission updated with these files:</p>';
$string['warnings'] = '<p class="warning">The following files were not processed:</p>';
$string['identifier'] = 'Identify user by';
$string['invalidident'] = 'Not a valid {$a}';
$string['invaliduser'] = '{$a} is not a submitter in this assignment';
$string['identnotunique'] = 'Identifier is not unique: multiple users match {$a->ident} = {$a->value}';
$string['emptydir'] = 'This folder is empty';
$string['includesrawimages'] = 'Includes raw images';