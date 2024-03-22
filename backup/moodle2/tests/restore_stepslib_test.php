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

namespace core_backup;

use backup;
use restore_controller;

/**
 * Tests for Moodle 2 restore steplib classes.
 *
 * @package core_backup
 * @copyright 2023 Ferran Recio <ferran@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_stepslib_test extends \advanced_testcase {
    /**
     * Setup to include all libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/backup/moodle2/restore_stepslib.php');
    }

    /**
     * Makes a backup of the course.
     *
     * @param \stdClass $course The course object.
     * @return string Unique identifier for this backup.
     */
    protected function backup_course(\stdClass $course): string {
        global $CFG, $USER;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new \backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup that has been made earlier.
     *
     * @param string $backupid The unique identifier of the backup.
     * @return int The new course id.
     */
    protected function restore_replacing_content(string $backupid): int {
        global $CFG, $USER;

        // Create course to restore into, and a user to do the restore.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Do restore to new course with default settings.
        $rc = new \restore_controller(
            $backupid,
            $course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_DELETING
        );

        $precheck = $rc->execute_precheck();
        $this->assertTrue($precheck);
        $rc->get_plan()->get_setting('role_assignments')->set_value(true);
        $rc->get_plan()->get_setting('permissions')->set_value(true);
        $rc->execute_plan();
        $rc->destroy();

        return $course->id;
    }

    /**
     * Test for the section structure step included elements.
     *
     * @covers \restore_section_structure_step::process_section
     */
    public function test_restore_section_structure_step(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 2, 'format' => 'topics']);
        // Section 2 has an existing delegate class.
        course_update_section(
            $course,
            $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]),
            [
                'component' => 'test_component',
                'itemid' => 1,
            ]
        );

        $backupid = $this->backup_course($course);
        $newcourseid = $this->restore_replacing_content($backupid);

        $originalsections = get_fast_modinfo($course->id)->get_section_info_all();
        $restoredsections = get_fast_modinfo($newcourseid)->get_section_info_all();

        $this->assertEquals(count($originalsections), count($restoredsections));

        $validatefields = ['name', 'summary', 'summaryformat', 'visible', 'component', 'itemid'];

        $this->assertEquals($originalsections[1]->name, $restoredsections[1]->name);

        foreach ($validatefields as $field) {
            $this->assertEquals($originalsections[1]->$field, $restoredsections[1]->$field);
            $this->assertEquals($originalsections[2]->$field, $restoredsections[2]->$field);
        }

    }

    /**
     * Test for the section structure step included elements.
     *
     * @covers \restore_move_module_questions_categories::process_section
     * @return void
     */
    public function test_restore_random_question_39(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Backup file is created from Moodle 3.9 site for testing the issue MDL-78902.
        $backupfile = 'test_random_question_39';

        // Extract backup file.
        $backupid = $backupfile;
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/$backupfile.mbz", $backuppath);

        // Restore the quiz activity in the backup from Moodle 3.9 to a new course.
        $coursecat = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $coursecat->id]);
        $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $USER->id, backup::TARGET_EXISTING_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get information about the quiz activity and confirm the references are correct.
        $modinfo = get_fast_modinfo($course->id);
        $quizzes = array_values($modinfo->get_instances_of('quiz'));
        // Get contextid for the restored quiz activity.
        $contextid = $quizzes[0]->context->id;
        $qcats = $DB->get_records('question_categories', ['contextid' => $contextid], 'parent');
        // Confirm there are 2 question categories for the restored quiz activity.
        $this->assertEquals(['top', 'Default for Test MDL-78902 quiz'], array_column($qcats, 'name'));
        // Get question_set_references records for the restored quiz activity.
        $references = $DB->get_records('question_set_references', ['usingcontextid' => $contextid]);
        foreach ($references as $reference) {
            $filtercondition = json_decode($reference->filtercondition);
            // Confirm the questionscontextid is set correctly, which is from filter question category id.
            $this->assertEquals($reference->questionscontextid,
                $qcats[$filtercondition->questioncategoryid]->contextid);
        }
    }
}
