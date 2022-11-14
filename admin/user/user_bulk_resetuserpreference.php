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
 * Script for bulk user reset user preference
 *
 * @package    core
 * @subpackage user
 * @copyright  2022 Catalyst IT Australia Pty Ltd
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('user_bulk_resetuserpreference_form.php');

admin_externalpage_setup('userbulk');
require_capability('moodle/user:update', context_system::instance());

$users = $SESSION->bulk_users;
$mform = new user_bulk_resetuserpreference_form();

if (empty($users) || $mform->is_cancelled()) {
    redirect(new moodle_url('/admin/user/user_bulk.php'));
}

echo $OUTPUT->header();

if ($data = $mform->get_data()) {
    $notifications = '';
    list($in, $params) = $DB->get_in_or_equal($SESSION->bulk_users);
    $rs = $DB->get_recordset_select('user', "id $in", $params);

    foreach ($rs as $user) {
        // Update part of user table.
        $update = false;
        if (isset($data->defaultpreference_maildisplay)
                && isset($CFG->defaultpreference_maildisplay)
                && $user->maildisplay != $CFG->defaultpreference_maildisplay) {
            $user->maildisplay = $CFG->defaultpreference_maildisplay;
            $update = true;
        }
        if (isset($data->defaultpreference_mailformat)
                && isset($CFG->defaultpreference_mailformat)
                && $user->mailformat != $CFG->defaultpreference_mailformat) {
            $user->mailformat = $CFG->defaultpreference_mailformat;
            $update = true;
        }
        if (isset($data->defaultpreference_maildigest)
                && isset($CFG->defaultpreference_maildigest)
                && $user->maildigest != $CFG->defaultpreference_maildigest) {
            $user->maildigest = $CFG->defaultpreference_maildigest;
            $update = true;
        }
        if (isset($data->defaultpreference_autosubscribe)
                && isset($CFG->defaultpreference_autosubscribe)
                && $user->autosubscribe != $CFG->defaultpreference_autosubscribe) {
            $user->autosubscribe = $CFG->defaultpreference_autosubscribe;
            $update = true;
        }
        if (isset($data->defaultpreference_trackforums)
            && isset($CFG->defaultpreference_trackforums) && $user->trackforums != $CFG->defaultpreference_trackforums) {
            $user->trackforums = $CFG->defaultpreference_trackforums;
            $update = true;
        }
        if ($update) {
            $DB->update_record('user', $user);
        }

        // Update user preference.
        if (isset($data->defaultpreference_core_contentbank_visibility)
            && isset($CFG->defaultpreference_core_contentbank_visibility)) {
            set_user_preference('core_contentbank_visibility', $CFG->defaultpreference_core_contentbank_visibility, $user->id);
        }
    }
    $rs->close();
    echo $OUTPUT->box_start('generalbox', 'notice');
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    $continue = new single_button(new moodle_url('/admin/user/user_bulk.php'), get_string('continue'), 'post');
    echo $OUTPUT->render($continue);
    echo $OUTPUT->box_end();
} else {
    echo $OUTPUT->heading(get_string('resetuserpreferences'));
    echo $OUTPUT->box_start();
    $mform->display();
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
