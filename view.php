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
 * Project Board course module controller.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . "/mod/projectboard/lib.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("projectboard", $id, 0, false, MUST_EXIST);
$activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
require_login($cm->course, false, $cm);
\mod_projectboard\access_manager::require_view($cm);
$context = \context_module::instance($cm->id);
$PAGE->set_url(new moodle_url("/mod/projectboard/view.php", ["id" => $id]));
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($activity->name));
$PAGE->requires->css(new moodle_url("/mod/projectboard/styles.css"));
$PAGE->requires->js_call_amd("mod_projectboard/board", "init", [(int)$id]);
$PAGE->requires->strings_for_js(["error", "loading", "conflict", "archive",
    "cancel", "save", "operationfailed"], "projectboard");
$completion = new completion_info($DB->get_record("course", ["id" => $activity->course]));
$completion->set_module_viewed($cm);
$event = \mod_projectboard\event\course_module_viewed::create([
    "objectid" => $activity->id, "context" => $context,
]);
$event->add_record_snapshot("course_modules", $cm);
$event->trigger();
$boards = \mod_projectboard\board_service::available($activity, $cm);
$boardid = optional_param("boardid", 0, PARAM_INT);
$selected = null;
foreach ($boards as $candidate) {
    if (!$boardid || $candidate->id == $boardid) {
        $selected = $candidate;
        break;
    }
}
if (!$selected && $boardid) {
    throw new moodle_exception("invalidaccess", "projectboard");
}
$data = \mod_projectboard\presentation\board_data::page($activity, $cm, $selected, $boards);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_projectboard/page", $data);
echo $OUTPUT->footer();
