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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Comment replies with file attachments and Moodle editor.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

require_once("../../config.php");
require_once($CFG->libdir . "/filelib.php");

$cmid = required_param("id", PARAM_INT);
$taskid = required_param("taskid", PARAM_INT);
$parentid = optional_param("parentid", 0, PARAM_INT);
$cm = get_coursemodule_from_id("projectboard", $cmid, 0, false, MUST_EXIST);
$activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
\mod_projectboard\access_manager::require_view($cm);
\mod_projectboard\access_manager::require_action("comment", $cm);
$task = \mod_projectboard\access_manager::require_task($taskid, $activity, $cm, true);
$context = \context_module::instance($cm->id);
if ($parentid && !$DB->record_exists("projectboard_comment", [
    "id" => $parentid, "taskid" => $taskid
])) {
    throw new moodle_exception("invalidaccess", "projectboard");
}
$url = new moodle_url("/mod/projectboard/comment.php", [
    "id" => $cmid, "taskid" => $taskid, "parentid" => $parentid
]);
$PAGE->set_url($url);
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_title(get_string("commentwithfiles", "projectboard"));
$PAGE->set_heading($activity->name);
$form = new \mod_projectboard\form\comment_editor_form($url, [
    "cmid" => $cmid, "taskid" => $taskid, "parentid" => $parentid, "context" => $context
]);
$redirect = new moodle_url("/mod/projectboard/view.php", [
    "id" => $cmid, "boardid" => $task->boardid
]);
if ($form->is_cancelled()) {
    redirect($redirect);
}
if ($data = $form->get_data()) {
    $draftid = (int)$data->files;
    $id = \mod_projectboard\comment_service::add($activity, $cm, [
        "taskid" => $taskid, "parentid" => $parentid,
        "content" => $data->content_editor["text"],
        "format" => (int)$data->content_editor["format"]
    ]);
    file_save_draft_area_files($draftid, $context->id,
        "mod_projectboard", "commentattachment", $id,
        ["subdirs" => 0, "maxfiles" => 5]);
    \mod_projectboard\completion_service::refresh($activity, $cm, (int)$USER->id);
    redirect($redirect);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("commentwithfiles", "projectboard"));
$form->display();
echo $OUTPUT->footer();
