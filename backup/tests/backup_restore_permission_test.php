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
 * Backup restore permission tests.
 *
 * @package   core_backup
 * @copyright Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once('backup_restore_base_test.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Testcase class for permission backup / restore functionality.
 */
class core_backup_backup_restore_permission_testcase extends core_backup_backup_restore_base_testcase {

    /**
     * Test for permission
     */
    public function test_backup_restore_permission_default_setting(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Confirm course1 has the capability for the user.
        $this->assertTrue(has_capability($records['capabilityname'], $records['course1context'], $records['user']));

        // Confirm course2 does not have the capability for the user.
        $this->assertFalse(has_capability($records['capabilityname'], $records['course2context'], $records['user']));

        // Perform backup and restore.
        $backupid = $this->perform_backup($records['course1']);
        $this->perform_restore($backupid, $records['course2']);

        // Confirm course2 has the capability for the user.
        $this->assertTrue(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for backup / restore without backup permission
     */
    public function test_backup_restore_without_backup_permission(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Set default setting to backup without permission.
        set_config('backup_general_permissions', 0, 'backup');

        // Perform backup and restore.
        $backupid = $this->perform_backup($records['course1']);
        $this->perform_restore($backupid, $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertFalse(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for backup / restore without restore permission
     */
    public function test_backup_restore_without_restore_permission(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Set default setting to restore without permission.
        set_config('restore_general_permissions', 0, 'restore');

        // Perform backup and restore.
        $backupid = $this->perform_backup($records['course1']);
        $this->perform_restore($backupid, $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertFalse(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for import with default setting (not copy)
     */
    public function test_backup_import_permission_default_setting(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Perform import.
        $this->perform_import($records['course1'], $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertFalse(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for import with default setting (not copy)
     */
    public function test_backup_import_permission_setting_on(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Set default setting to restore without permission.
        set_config('backup_import_permissions', 1, 'backup');

        // Perform import.
        $this->perform_import($records['course1'], $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertTrue(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for auto backup with default setting
     */
    public function test_backup_auto_backup_permission_default_setting(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Perform import.
        $backupid = $this->perform_auto_backup($records['course1']);
        $this->perform_restore($backupid, $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertTrue(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Test for auto backup with setting off
     */
    public function test_backup_auto_backup_permission_setting_off(): void {

        // Prepare courses.
        $records = $this->prepare_courses_for_permission_test();

        // Set default setting to restore without permission.
        set_config('backup_auto_permissions', 0, 'backup');

        // Perform import.
        $this->perform_import($records['course1'], $records['course2']);

        // Confirm course2 does not have the capability for the user.
        $this->assertFalse(has_capability($records['capabilityname'], $records['course2context'], $records['user']));
    }

    /**
     * Create test courses for permission test
     * @return array compact('course1', 'course2', 'user', 'capabilityname', 'course1context', 'course2context')
     * @throws dml_exception
     */
    protected function prepare_courses_for_permission_test() {
        global $DB;

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course1context = context_course::instance($course1->id);
        $course2 = $generator->create_course();
        $course2context = context_course::instance($course2->id);
        $capabilityname = 'enrol/manual:enrol';
        $user = $generator->create_user();

        // Set additional permission for course 1.
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        role_change_permission($teacherrole->id, $course1context, $capabilityname, CAP_ALLOW);

        // Enrol to the courses.
        $generator->enrol_user($user->id, $course1->id, $teacherrole->id);
        $generator->enrol_user($user->id, $course2->id, $teacherrole->id);

        return compact('course1', 'course2', 'user', 'capabilityname', 'course1context', 'course2context');
    }

}
