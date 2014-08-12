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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'adhoctaskmanager',
        get_string('pluginname', 'tool_adhoc'),
        new \moodle_url("/admin/tool/adhoc/index.php")
    ));

    $settings = new admin_settingpage('tool_adhoc', get_string('pluginname', 'tool_adhoc'));

    $settings->add(new admin_setting_configtext(
        'tool_adhoc/nagios_warning_threshhold',
        'Adhoc queue threshold (warning)',
        'The maximum allowed tasks in the adhoc queue before a warning is triggered.',
        10, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_adhoc/nagios_error_threshhold',
        'Adhoc queue threshold (error)',
        'The maximum allowed tasks in the adhoc queue before an error is triggered.',
        25, PARAM_INT
    ));
}