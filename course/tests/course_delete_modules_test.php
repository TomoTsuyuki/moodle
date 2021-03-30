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
 * Tests for the \core_course\task\course_delete_modules class.
 *
 * @package    core
 * @subpackage course
 * @copyright  2021 Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tests\core_course;

defined('MOODLE_INTERNAL') || die();

use core_course\task\course_delete_modules;

/**
 * Tests for the \core_course\task\course_delete_modules class.
 *
 * @package    core
 * @subpackage course
 * @copyright  2021 Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_delete_modules_testcase extends \advanced_testcase {

    /**
     * Test to have a message in the exception.
     */
    public function test_delete_module_exception() {
        global $DB;
        $this->resetAfterTest();

        // Generate test data.
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $assign = $generator->create_module('assign', array('course' => $course));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);

        // Modify module name to make an exception in the course_delete_modules task.
        $module = $DB->get_record('modules', array('id' => $assigncm->module), '*', MUST_EXIST);
        $module->name = 'Test';
        $DB->update_record('modules', $module);

        // Expect exceptions.
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('cannotdeletemodulemissinglib');
        $this->expectExceptionMessage('course/lib.php');
        // Get line numbers array which contains the exception name.
        $lines = array_keys(preg_grep("/cannotdeletemodulemissinglib/", file('course/lib.php')));
        // Increase 1 to keys.
        $lines = array_map(function($key) {
            return ++$key;
        }, $lines);
        $regex = "/(\(" . implode('\))|(\(', $lines) . "\))/";
        $this->expectExceptionMessageMatches($regex);

        // Execute the task.
        $removaltask = new course_delete_modules();
        $data = array(
                'cms' => [$assigncm],
                'userid' => $user->id,
                'realuserid' => $user->id
        );
        $removaltask->set_custom_data($data);
        $removaltask->execute();
    }
}