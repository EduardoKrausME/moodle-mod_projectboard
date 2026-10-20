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
 * Evidence upload page and file persistence.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->libdir . "/filelib.php");
require_once($CFG->libdir . "/formslib.php");

$cmid = required_param("id", PARAM_INT);
$taskid = required_param("taskid", PARAM_INT);
$cm = get_coursemodule_from_id("projectboard", $cmid, 0, false, MUST_EXIST);
$activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
require_login($cm->course, false, $cm);
\mod_projectboard\access_manager::require_view($cm);
\mod_projectboard\access_manager::require_action("addevidence", $cm);
$task = \mod_projectboard\access_manager::require_task($taskid, $activity, $cm, true);
$context = \context_module::instance($cm->id);
$url = new moodle_url("/mod/projectboard/evidence.php", ["id" => $cmid, "taskid" => $taskid]);
$PAGE->set_url($url);
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_title(get_string("addevidence", "projectboard"));
$PAGE->set_heading($activity->name);
$form = new \mod_projectboard\form\evidence_form($url, ["cmid" => $cmid, "taskid" => $taskid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/projectboard/view.php", ["id" => $cmid]));
}
if ($data = $form->get_data()) {
    $draftid = (int)$data->files;
    $draftcontext = \context_user::instance($USER->id);
    $files = get_file_storage()->get_area_files($draftcontext->id,
        "user", "draft", $draftid, "id", false);
    if (!$files) {
        throw new moodle_exception("requiresfiles", "projectboard");
    }
    $transaction = $DB->start_delegated_transaction();
    $evidenceid = $DB->insert_record("projectboard_evidence", (object)[
        "taskid" => $taskid, "userid" => $USER->id,
        "description" => clean_param($data->description, PARAM_TEXT), "timecreated" => time(),
    ]);
    file_save_draft_area_files($draftid, $context->id, "mod_projectboard",
        "evidence", $evidenceid, ["subdirs" => 0, "maxfiles" => 10]);
    \mod_projectboard\history_service::record($taskid, "evidence_added", null, $evidenceid);
    $transaction->allow_commit();
    \mod_projectboard\completion_service::refresh($activity, $cm, (int)$USER->id);
    redirect(new moodle_url("/mod/projectboard/view.php", ["id" => $cmid, "boardid" => $task->boardid]));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("addevidence", "projectboard"));
$form->display();
echo $OUTPUT->footer();
