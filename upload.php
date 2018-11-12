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
 * Show the form for uploading, or handle an upload if one was submitted.
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:editothersubmission', $context);

$here = new moodle_url('/local/assignbulk/upload.php', ['id' => $id]);
$PAGE->set_url($here);
$PAGE->set_context($context);
$PAGE->set_cm($cm);

$assign = new assign($context, $cm, $course);
$output = $assign->get_renderer();

$mform = new local_assignbulk\upload_form();

if ($mform->is_cancelled()) {
    $continue = new moodle_url('/mod/assign/view.php', ['id' => $id, 'action' => 'grading']);
    redirect($continue);
}

$subhead = $OUTPUT->heading(get_string('bulkuploadsubmissions', 'local_assignbulk'), 3);
echo $output->render(new assign_header($assign->get_instance(),
                                              $context,
                                              false,
                                              $id,
                                              null,
                                              $subhead));


$showform = true;

if ($data = $mform->get_data()) {
    $commit = isset($data->submitbutton);
    $uploader = new local_assignbulk\bulk_uploader($assign, $data->identifier);
    $progress = new \core\progress\display_if_slow();
    $progress->set_display_names(true);
    $feedback = $uploader->execute($data->submissions, $commit, $progress);
    $feedback->preview = !$commit;
    $feedback->anywarnings = count($feedback->warnings);

    echo $OUTPUT->render_from_template('local_assignbulk/complete', $feedback);

    if ($commit && empty($feedback->warnings)) {
        $showform = false;
        $continue = new moodle_url('/mod/assign/view.php', ['id' => $id, 'action' => 'grading']);
        echo $OUTPUT->continue_button($continue);
    }
}

if ($showform) {
    echo $OUTPUT->render_from_template('local_assignbulk/upload', []);

    $data = new stdClass;
    $data->id = $cm->id;

    $uploader = new local_assignbulk\bulk_uploader($assign, 'username');
    $draftitemid = $uploader->prepare_draft_area();

    $data->submissions = $draftitemid;

    $mform->set_data($data);

    $mform->display();
}

echo $OUTPUT->footer();