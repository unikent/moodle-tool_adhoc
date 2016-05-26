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
 * Queue management settings.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/adminlib.php");

class tool_adhoc_setting_managequeues extends admin_setting {
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('tool_adhoc_manageui', get_string('managequeues', 'tool_adhoc'), '', '');
    }

    /**
     * Always returns true, does nothing.
     *
     * @return true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true, does nothing.
     *
     * @return true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Always returns '', does not write anything.
     *
     * @param mixed $data ignored
     * @return string Always returns ''
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Checks if $query is one of the available queue plugins.
     *
     * @param string $query The string to search for
     * @return bool Returns true if found, false if not
     */
    public function is_related($query) {
        if (parent::is_related($query)) {
            return true;
        }

        $query = core_text::strtolower($query);
        $plugins = \core_component::get_plugin_list_with_class('queue', 'queue');
        foreach ($plugins as $plugin => $fulldir) {
            if (strpos(core_text::strtolower($plugin), $query) !== false) {
                return true;
            }
            $localised = get_string('pluginname', $plugin);
            if (strpos(core_text::strtolower($localised), $query) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds the XHTML to display the control.
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        // Display strings.
        $strup = get_string('up');
        $strdown = get_string('down');
        $strsettings = get_string('settings');
        $strready = get_string('storeready', 'core_cache');
        $strenable = get_string('enable');
        $strdisable = get_string('disable');
        $struninstall = get_string('uninstallplugin', 'core_admin');
        $strversion = get_string('version');

        $pluginmanager = core_plugin_manager::instance();
        $available = \core_component::get_plugin_list_with_class('queue', 'queue');
        $enabled = get_config('tool_adhoc', 'enabled_queues');
        if (!$enabled) {
            $enabled = array();
        } else {
            $enabled = array_flip(explode(',', $enabled));
        }

        $allstores = array();
        foreach ($enabled as $key => $queue) {
            $allstores[$key] = true;
            $enabled[$key] = true;
        }
        foreach ($available as $key => $queue) {
            $allstores[$key] = true;
            $available[$key] = true;
        }

        $return = $OUTPUT->heading(get_string('availqueues', 'tool_adhoc'), 3, 'main', true);
        $return .= $OUTPUT->box_start('generalbox queueui');

        $table = new html_table();
        $table->head = array(
            get_string('name'),
            $strversion,
            $strenable,
            $strup . '/' . $strdown,
            $strready,
            $strsettings,
            $struninstall
        );
        $table->colclasses = array(
            'leftalign',
            'centeralign',
            'centeralign',
            'centeralign',
            'centeralign',
            'centeralign',
            'centeralign'
        );
        $table->id = 'queueplugins';
        $table->attributes['class'] = 'admintable generaltable';
        $table->data = array();

        // Iterate through queue plugins and add to the display table.
        $updowncount = 1;
        $queuecount = count($enabled);
        $url = new moodle_url('/admin/tool/adhoc/queues.php', array('sesskey' => sesskey()));
        $printed = array();
        foreach ($allstores as $queue => $unused) {
            $queueobj = \tool_adhoc\manager::get_queue($queue);
            if (!$queueobj) {
                continue;
            }

            $plugininfo = $pluginmanager->get_plugin_info($queue);
            $version = get_config($queue, 'version');
            if ($version === false) {
                $version = '';
            }

            if (get_string_manager()->string_exists('pluginname', $queue)) {
                $name = get_string('pluginname', $queue);
            } else {
                $name = $queue;
            }

            // Hide/show links.
            if (isset($enabled[$queue])) {
                $aurl = new moodle_url($url, array('action' => 'disable', 'queue' => $queue));
                $hideshow = "<a href=\"$aurl\">";
                $hideshow .= "<img src=\"" . $OUTPUT->pix_url('t/hide') . "\" class=\"iconsmall\" alt=\"$strdisable\" /></a>";
                $isenabled = true;
                $displayname = "<span>$name</span>";
            } else {
                if (isset($available[$queue])) {
                    $aurl = new moodle_url($url, array('action' => 'enable', 'queue' => $queue));
                    $hideshow = "<a href=\"$aurl\">";
                    $hideshow .= "<img src=\"" . $OUTPUT->pix_url('t/show') . "\" class=\"iconsmall\" alt=\"$strenable\" /></a>";
                    $isenabled = false;
                    $displayname = "<span class=\"dimmed_text\">$name</span>";
                } else {
                    $hideshow = '';
                    $isenabled = false;
                    $displayname = '<span class="notifyproblem">' . $name . '</span>';
                }
            }
            if ($PAGE->theme->resolve_image_location('icon', $queue, false)) {
                $icon = $OUTPUT->pix_icon('icon', '', $queue, array('class' => 'icon pluginicon'));
            } else {
                $icon = $OUTPUT->pix_icon('spacer', '', 'moodle', array('class' => 'icon pluginicon noicon'));
            }

            // Up/down link (only if queue is enabled).
            $updown = '';
            if ($isenabled) {
                if ($updowncount > 1) {
                    $aurl = new moodle_url($url, array('action' => 'up', 'queue' => $queue));
                    $updown .= "<a href=\"$aurl\">";
                    $updown .= "<img src=\"" . $OUTPUT->pix_url('t/up') . "\" alt=\"$strup\" class=\"iconsmall\" /></a>&nbsp;";
                } else {
                    $updown .= "<img src=\"" . $OUTPUT->pix_url('spacer') . "\" class=\"iconsmall\" alt=\"\" />&nbsp;";
                }
                if ($updowncount < $queuecount) {
                    $aurl = new moodle_url($url, array('action' => 'down', 'queue' => $queue));
                    $updown .= "<a href=\"$aurl\">";
                    $updown .= "<img src=\"" . $OUTPUT->pix_url('t/down') . "\" alt=\"$strdown\" class=\"iconsmall\" /></a>";
                } else {
                    $updown .= "<img src=\"" . $OUTPUT->pix_url('spacer') . "\" class=\"iconsmall\" alt=\"\" />";
                }
                ++$updowncount;
            }

            // Is ready check.
            $isready = '';
            if ($queueobj->is_ready()) {
                $isready = $OUTPUT->pix_icon('i/valid', '1');
            }

            // Add settings link.
            if (!$version) {
                $settings = '';
            } else {
                if ($surl = $plugininfo->get_settings_url()) {
                    $settings = html_writer::link($surl, $strsettings);
                } else {
                    $settings = '';
                }
            }

            // Add uninstall info.
            $uninstall = '';
            if ($uninstallurl = core_plugin_manager::instance()->get_uninstall_url($queue, 'manage')) {
                $uninstall = html_writer::link($uninstallurl, $struninstall);
            }

            // Add a row to the table.
            $table->data[] = array($icon . $displayname, $version, $hideshow, $updown, $isready, $settings, $uninstall);

            $printed[$queue] = true;
        }

        $return .= html_writer::table($table);
        $return .= get_string('configlogplugins', 'tool_log') . '<br />' . get_string('tablenosave', 'admin');
        $return .= $OUTPUT->box_end();
        return highlight($query, $return);
    }
}
