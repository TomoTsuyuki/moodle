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
 * form for bulk user multi cohort add
 *
 * @package    core
 * @subpackage user
 * @copyright  2022 Catalyst IT Australia Pty Ltd
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Bulk user action form for reset user preferences
 *
 * @package    core
 * @copyright  2022 Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_bulk_resetuserpreference_form extends moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_maildisplay', get_string('emaildisplay'));
        }
        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_mailformat', get_string('emailformat'));
        }
        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_maildigest', get_string('emaildigest'));
        }
        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_autosubscribe', get_string('autosubscribe'));
        }
        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_trackforums', get_string('trackforums'));
        }
        if (isset($CFG->defaultpreference_maildisplay)) {
            $mform->addElement('checkbox', 'defaultpreference_core_contentbank_visibility',
                get_string('visibilitypref', 'core_contentbank'));
        }

        $this->add_action_buttons(true, get_string('resetuserpreferences'));
    }
}
