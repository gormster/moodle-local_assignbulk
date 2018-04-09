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

    // Now we're sure we're in the right place, add a button to the navtree.

    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (empty($node)) {
        return;
    }

    $link = new moodle_url('/local/assignbulk/upload.php', array('id' => $cm->id));
    $node->add(get_string('bulkuploadsubmissions', 'local_assignbulk'), $link, navigation_node::TYPE_SETTING);

}