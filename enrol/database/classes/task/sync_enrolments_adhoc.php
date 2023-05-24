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
 * Sync enrolments adhoc task
 * @package   enrol_database
 * @copyright Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_database\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class sync_enrolments
 * @package   enrol_database
 * @copyright 2018 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_enrolments_adhoc extends \core\task\adhoc_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('syncenrolmentstaskadhoc', 'enrol_database');
    }

    /**
     * Run task for synchronising users.
     */
    public function execute() {

        $courses = $this->get_custom_data()->courses;
        $trace = new \text_progress_trace();

        if (!enrol_is_enabled('database')) {
            $trace->output('Plugin not enabled');
            return;
        }

        $enrol = enrol_get_plugin('database');
        $enrol->sync_enrolments($trace, null, $courses);
    }
}
