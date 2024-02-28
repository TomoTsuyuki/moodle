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
     * Test for the section structure step included elements.
     *
     * @covers \restore_move_module_questions_categories::process_section
     * @return void
     */
    public function test_restore_random_question(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $backupfile = 'test_random_question_39';

        // Extract backup file.
        $backupid = $backupfile;
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/$backupfile.mbz", $backuppath);

        // Restore the quiz activity in the backup to a new course.
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
        $contextid = $quizzes[0]->context->id;
        $qcats = $DB->get_records('question_categories', ['contextid' => $contextid], 'parent');
        $this->assertEquals(['top', 'Default for Test MDL-78902 quiz'], array_column($qcats, 'name'));
        $references = $DB->get_records('question_set_references', ['usingcontextid' => $contextid]);
        foreach ($references as $reference) {
            $filtercondition = json_decode($reference->filtercondition);
            $this->assertEquals($reference->questionscontextid,
                $qcats[$filtercondition->questioncategoryid]->contextid);
        }
    }
}
