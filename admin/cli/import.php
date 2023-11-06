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
 * This script allows to import a course from CLI.
 *
 * @package    core
 * @subpackage cli
 * @author     Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . "/backup/util/includes/restore_includes.php");

list($options, $unrecognized) = cli_get_params([
    'srccourseid' => '',
    'dstcategoryid' => '',
    'srccmid' => '',
    'dstcourseid' => '',
    'showdebugging' => false,
    'help' => false,
], [
    's' => 'showdebugging',
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] ||
    !(($options['srccourseid'] && $options['dstcourseid'])
        || ($options['srccourseid'] && $options['dstcategoryid'])
        || ($options['srccmid'] && $options['dstcourseid']))) {
    $help = <<<EOL
Import course into provided category, or course module into provided course.

Options:
    --srccourseid=INT   Source course ID to backup.
    --dstcategoryid=INT Destination category ID to restore.
    --srccmid=INT       Source course module ID to backup.
    --dstcourseid=INT   Destination course ID to restore.
-s, --showdebugging     Show developer level debugging information
-h, --help              Print out this help.

Example1: (Import from course into category)
\$sudo -u www-data /usr/bin/php admin/cli/import.php --srccourseid=12 --dstcategoryid=1\n

Example2: (Import from course module into course)
\$sudo -u www-data /usr/bin/php admin/cli/import.php --srccmid=21 --dstcourseid=11\n
EOL;

    echo $help;
    exit(0);
}

if ($options['showdebugging']) {
    set_debugging(DEBUG_DEVELOPER, true);
}

if (!$admin = get_admin()) {
    throw new \moodle_exception('noadmins');
}

if (!empty($options['srccourseid']) && !empty($options['dstcourseid'])) {
    // Import from course into course.
    if (!$srccourse = $DB->get_record('course', ['id' => $options['srccourseid']], 'id')) {
        throw new \moodle_exception('invalidcourseid');
    }
    if (!$dstcourse = $DB->get_record('course', ['id' => $options['dstcourseid']], 'id')) {
        throw new \moodle_exception('invalidcourseid');
    }

    cli_heading('Import from course:' . $options['srccourseid'] . ' into course:' . $options['dstcourseid']);
    $bc = new backup_controller(backup::TYPE_1COURSE, $srccourse->id, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $admin->id);
    $backupid = $bc->get_backupid();
    $bc->execute_plan();
    $bc->destroy();
    cli_heading('Backup completed. Start to restore.');

    $rc = new restore_controller($backupid, $dstcourse->id, backup::INTERACTIVE_NO,
        backup::MODE_SAMESITE, $admin->id, backup::TARGET_EXISTING_ADDING);
    $rc->execute_precheck();
    $rc->execute_plan();
    $rc->destroy();
    cli_heading(get_string('restoredcourseid', 'backup', $dstcourse->id));
} else if (!empty($options['srccourseid']) && !empty($options['dstcategoryid'])) {
    // Import from course into category.
    if (!$course = $DB->get_record('course', ['id' => $options['srccourseid']], 'id')) {
        throw new \moodle_exception('invalidcourseid');
    }
    if (!$category = $DB->get_record('course_categories', ['id' => $options['dstcategoryid']], 'id')) {
        throw new \moodle_exception('invalidcategoryid');
    }

    cli_heading('Import from course:' . $options['srccourseid'] . ' into category:' . $options['dstcategoryid']);
    $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $admin->id);
    $backupid = $bc->get_backupid();
    $bc->execute_plan();
    $bc->destroy();

    cli_heading('Backup completed. Start to restore.');
    list($fullname, $shortname) = restore_dbops::calculate_course_names(0, get_string('restoringcourse', 'backup'),
        get_string('restoringcourseshortname', 'backup'));
    $courseid = restore_dbops::create_new_course($fullname, $shortname, $category->id);
    $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_NO,
        backup::MODE_SAMESITE, $admin->id, backup::TARGET_NEW_COURSE);
    $rc->execute_precheck();
    $rc->execute_plan();
    $rc->destroy();
    cli_heading(get_string('restoredcourseid', 'backup', $courseid));
} else if (!empty($options['srccmid']) && !empty($options['dstcourseid'])) {
    // Import from course module into course.
    if (!$cm = $DB->get_record('course_modules', ['id' => $options['srccmid']], 'id')) {
        throw new \moodle_exception('invalidcoursemoduleid', 'error', '', $options['srccmid']);
    }
    if (!$course = $DB->get_record('course', ['id' => $options['dstcourseid']], 'id')) {
        throw new \moodle_exception('invalidcourseid');
    }

    cli_heading('Import from cmid:' . $options['srccmid'] . ' into course:' . $options['dstcourseid']);
    $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $admin->id);
    $backupid = $bc->get_backupid();
    $bc->execute_plan();
    $bc->destroy();
    cli_heading('Backup completed. Start to restore.');

    $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_NO,
        backup::MODE_SAMESITE, $admin->id, backup::TARGET_EXISTING_ADDING);
    $rc->execute_precheck();
    $rc->execute_plan();
    $rc->destroy();
    cli_heading(get_string('restoredcourseid', 'backup', $course->id));
} else {
    throw new \moodle_exception('invalidoption');
}

exit(0);
