<?php

defined('MOODLE_INTERNAL') || die;

function local_assignbulk_extend_settings_navigation(settings_navigation $nav, context $context) {

    if (!($context instanceof context_module)) {
        return;
    }

    if (!has_capability('mod/assign:editothersubmission', $context)) {
        return;
    }

    list($course, $cm) = get_course_and_cm_from_cmid($context->instanceid);

    if ($cm->modname != 'assign') {
        return;
    }

    $assign = new assign($context, $cm, $course);
    $plugin = $assign->get_submission_plugin_by_type('file');
    if (!$plugin->is_enabled()) {
        return;
    }


    // Now we're sure we're in the right place, add a button to the navtree

    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (empty($node)) {
        return;
    }

    $link = new moodle_url('/local/assignbulk/upload.php', array('id' => $cm->id));
    $node->add(get_string('bulkuploadsubmissions', 'local_assignbulk'), $link, navigation_node::TYPE_SETTING);

}

?>