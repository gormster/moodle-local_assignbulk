<?php

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
    $feedback = $uploader->execute($data->submissions, $commit);
    $feedback->preview = !$commit;
    $feedback->anywarnings = count($feedback->warnings);

    echo $OUTPUT->render_from_template('local_assignbulk/complete', $feedback);

    if (empty($feedback->warnings)) {
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