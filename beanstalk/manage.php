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
 * This page displays beanstalk stats.
 *
 * @package    tool_adhoc
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('beanstalktaskmanager');

echo $OUTPUT->header();
echo $OUTPUT->heading('Beanstalk manager');

$beanstalk = new \tool_adhoc\beanstalk();
$info = $beanstalk->statsTube($beanstalk->get_tube());
if ($info['current-jobs-buried'] > 0) {
    print_r($beanstalk->peekBuried($beanstalk->get_tube()));
}

echo $OUTPUT->footer();