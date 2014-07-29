<?php
// This file is part of Moodle - http://moodle.org/
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
 * Output rendering for the plugin.
 *
 * @package    local_adhoc
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Implements the plugin renderer
 *
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_adhoc_renderer extends plugin_renderer_base {
    /**
     * This function will render a table with all the adhoc tasks.
     *
     * @return string HTML to output.
     */
    public function adhoc_tasks_table() {
        global $CFG, $DB;

        $tasks = $DB->get_records('task_adhoc');

        if (empty($tasks)) {
            return \html_writer::tag('p', get_string('notasks', 'local_adhoc'));
        }

        $table = new html_table();
        $table->head  = array(
            get_string('id', 'local_adhoc'),
            get_string('component', 'tool_task'),
            get_string('delete'),
            get_string('name'),
            get_string('nextruntime', 'tool_task'),
            get_string('faildelay', 'tool_task'),
            get_string('customdata', 'local_adhoc'),
            get_string('blocking', 'local_adhoc')
        );
        $table->attributes['class'] = 'admintable generaltable';

        $data = array();
        foreach ($tasks as $task) {
            $configureurl = new moodle_url('/local/adhoc/index.php', array(
                'action' => 'delete',
                'task' => $task->id
            ));

            $editlink = $this->action_icon($configureurl, new pix_icon('t/delete', get_string('deletetask', 'local_adhoc')));

            $idcell = new html_table_cell($task->id);
            $idcell->header = true;

            $component = $task->component;
            list($type, $plugin) = core_component::normalize_component($component);
            if ($type === 'core') {
                $componentcell = new html_table_cell(get_string('corecomponent', 'tool_task'));
            } else {
                if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
                    $plugininfo->init_display_name();
                    $componentcell = new html_table_cell($plugininfo->displayname);
                } else {
                    $componentcell = new html_table_cell($component);
                }
            }

            $row = new html_table_row(array(
                $idcell,
                $componentcell,
                new html_table_cell($editlink),
                new html_table_cell($task->classname),
                new html_table_cell($task->nextruntime),
                new html_table_cell($task->faildelay),
                new html_table_cell($task->customdata),
                new html_table_cell($task->blocking)
            ));

            $data[] = $row;
        }

        $table->data = $data;
        return html_writer::table($table);
    }
}
