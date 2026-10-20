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
 * Authorized project reporting and CSV export.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$export = optional_param("export", 0, PARAM_BOOL);
$priority = optional_param("priority", "", PARAM_ALPHA);
$status = optional_param("status", "", PARAM_TEXT);
$search = optional_param("search", "", PARAM_TEXT);
$cm = get_coursemodule_from_id("projectboard", $id, 0, false, MUST_EXIST);
$activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
require_login($cm->course, false, $cm);
\mod_projectboard\access_manager::require_view($cm);
\mod_projectboard\access_manager::require_action("viewreports", $cm);
$PAGE->set_url(new moodle_url("/mod/projectboard/report.php", ["id" => $id]));
$PAGE->set_cm($cm);
$PAGE->set_context(\context_module::instance($cm->id));
$PAGE->set_title(get_string("report", "projectboard"));
$PAGE->set_heading($activity->name);
$rows = \mod_projectboard\report_service::rows($activity, $cm);
$rows = array_values(array_filter($rows, static function($row) use ($priority, $status, $search) {
    return (!$priority || $row["priority"] === $priority) &&
        (!$status || $row["status"] === $status) &&
        (!$search || stripos($row["title"], $search) !== false);
}));
if ($export) {
    require_once($CFG->libdir . "/csvlib.class.php");
    $writer = new \csv_export_writer("comma");
    $writer->set_filename("projectboard-" . $activity->id);
    $writer->add_data(["Board", "Task ID", "Task", "Status", "Priority", "Due", "Assignees", "Overdue"]);
    foreach ($rows as $row) {
        $writer->add_data(array_values($row));
    }
    $writer->download_file();
    exit;
}
$data = [
    "cmid" => $id, "title" => get_string("report", "projectboard"),
    "rows" => $rows, "hasrows" => !empty($rows),
    "priority" => $priority, "status" => $status, "search" => $search,
    "exporturl" => (new moodle_url("/mod/projectboard/report.php", [
        "id" => $id, "export" => 1, "priority" => $priority, "status" => $status, "search" => $search,
    ]))->out(false),
    "boardurl" => (new moodle_url("/mod/projectboard/view.php", ["id" => $id]))->out(false),
    "strings" => [
        "board" => get_string("board", "projectboard"),
        "taskid" => get_string("taskid", "projectboard"),
        "tasktitle" => get_string("tasktitle", "projectboard"),
        "priority" => get_string("priority", "projectboard"),
        "status" => get_string("status", "projectboard"),
        "duedate" => get_string("duedate", "projectboard"),
        "assignees" => get_string("assignees", "projectboard"),
        "late" => get_string("late", "projectboard"),
        "search" => get_string("search", "projectboard"),
        "filter" => get_string("filter", "projectboard"),
        "exportcsv" => get_string("exportcsv", "projectboard"),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_projectboard/report", $data);
echo $OUTPUT->footer();
