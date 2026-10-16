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
 * Course activity entry point.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("projectboard", $id, 0, false, MUST_EXIST);
$activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
\mod_projectboard\access_manager::require_view($cm);
$PAGE->set_url(new moodle_url("/mod/projectboard/view.php", ["id" => $cm->id]));
$PAGE->set_cm($cm);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($activity->name));
$PAGE->set_context(\context_module::instance($cm->id));
$PAGE->requires->css(new moodle_url("/mod/projectboard/styles.css"));
$PAGE->requires->js_call_amd("mod_projectboard/board", "init", [(int)$cm->id]);
$PAGE->requires->strings_for_js(["error", "loading", "conflict", "archive",
    "cancel", "save", "operationfailed"], "projectboard");

$boards = \mod_projectboard\board_service::available($activity, $cm);
$requested = optional_param("boardid", 0, PARAM_INT);
$board = null;
foreach ($boards as $candidate) {
    if (!$requested || $requested == $candidate->id) {
        $board = $candidate;
        break;
    }
}
if (!$board && $requested) {
    throw new moodle_exception("invalidaccess", "projectboard");
}
$view = \mod_projectboard\presentation\board_data::page($activity, $cm, $board, $boards);
$PAGE->set_title($activity->name);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_projectboard/page", $view);
echo $OUTPUT->footer();
