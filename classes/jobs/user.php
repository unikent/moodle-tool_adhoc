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
 * Beanstalk jobs.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc\jobs;

/**
 * User tasks.
 */
class user
{
    /**
     * Pre-populate caches a user might be interested in.
     */
    public function on_login($userid) {
        global $CFG;

        require_once("{$CFG->libdir}/enrollib.php");

        cli_writeln("Processing user login: {$userid}");

        // Grab a ist of courses they're in.
        $courses = enrol_get_users_courses($userid);
        foreach ($courses as $course) {
            cli_writeln("Pre-caching course: " . $course->id);
            //get_fast_modinfo();
            \course_modinfo::build_course_cache($course);
        }

        return \tool_adhoc\beanstalk::STATUS_OK;
    }

    /**
     * Pre-populate caches a user might be interested in.
     */
    public function on_logout($userid) {
        cli_writeln("Processing user logout: {$userid}");

        return \tool_adhoc\beanstalk::STATUS_OK;
    }
}
