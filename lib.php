<?php

defined('MOODLE_INTERNAL') || die;

function local_assignbulk_extend_settings_navigation(settings_navigation $nav, context $context) {

    if (!($context instanceof context_module)) {
        return;
    }

    if (!has_capability('mod/assign:editothersubmission', $context)) {
        return;
    }

    $coursecontext = $context->get_course_context();

    $cmid = $context->instanceid;
    $courseid = $coursecontext->instanceid;

    $modinfo = get_fast_modinfo($courseid)->cms[$cmid];

    if ($modinfo->modname != 'assign') {
        return;
    }

    // Now we're sure we're in the right place, add a button to the navtree

    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (empty($node)) {
        return;
    }

    $link = new moodle_url('/local/assignbulk/upload.php', array('id' => $cmid));
    $node->add(get_string('bulkuploadsubmissions', 'local_assignbulk'), $link, navigation_node::TYPE_SETTING);

}

?>