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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Authenticated, board-scoped AJAX commands.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\external;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Provides operation functionality.
 */
final class operation extends external_api {
    /**
     * Handles execute parameters operations for Project Board.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Course module ID"),
            "action" => new external_value(PARAM_ALPHANUMEXT, "Operation"),
            "payload" => new external_value(PARAM_RAW, "JSON operation payload"),
        ]);
    }

    /**
     * Handles execute operations for Project Board.
     */
    public static function execute(int $cmid, string $action, string $payload): array {
        global $DB, $PAGE, $OUTPUT;
        $params = self::validate_parameters(self::execute_parameters(),
            ["cmid" => $cmid, "action" => $action, "payload" => $payload]);
        $cm = get_coursemodule_from_id("projectboard", $params["cmid"], 0, false, MUST_EXIST);
        $activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
        \mod_projectboard\access_manager::require_view($cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        $PAGE->set_context($context);
        $data = json_decode($params["payload"], true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception("Expected a JSON object");
        }
        $boardid = (int)($data["boardid"] ?? 0);
        $taskid = (int)($data["taskid"] ?? 0);
        $changed = false;
        switch ($params["action"]) {
            case "refresh":
                break;
            case "task_details":
                $task = \mod_projectboard\access_manager::require_task($taskid, $activity, $cm);
                $taskdata = \mod_projectboard\presentation\board_data::task($activity, $cm, $task);
                return ["html" => "", "dialog" => $OUTPUT->render_from_template("mod_projectboard/task", $taskdata),
                    "taskid" => $taskid];
            case "task_create":
                $taskid = \mod_projectboard\task_service::create($activity, $cm, $data);
                $changed = true;
                break;
            case "task_update":
                \mod_projectboard\task_service::update($activity, $cm, $data);
                $changed = true;
                break;
            case "task_move":
                \mod_projectboard\task_service::move($activity, $cm, $data);
                $changed = true;
                break;
            case "task_assign":
                \mod_projectboard\task_service::assign($activity, $cm, $data);
                $changed = true;
                break;
            case "review_submit":
                \mod_projectboard\review_service::submit($activity, $cm, $data);
                $changed = true;
                break;
            case "review_approve":
            case "review_return":
                \mod_projectboard\review_service::evaluate($activity, $cm, $data,
                    $action === "review_approve");
                $changed = true;
                break;
            case "task_archive":
                \mod_projectboard\task_service::archive($activity, $cm, $data);
                $changed = true;
                $taskid = 0;
                break;
            case "task_duplicate":
                $taskid = \mod_projectboard\task_service::duplicate($activity, $cm, $data);
                $changed = true;
                break;
            case "check_add":
            case "check_toggle":
            case "check_delete":
            case "check_order":
                \mod_projectboard\checklist_service::execute($activity, $cm, $action, $data);
                $changed = true;
                break;
            case "dep_add":
                \mod_projectboard\dependency_service::add($activity, $cm,
                    $taskid, (int)($data["dependsid"] ?? 0));
                $changed = true;
                break;
            case "dep_delete":
                \mod_projectboard\dependency_service::remove($activity, $cm,
                    $taskid, (int)($data["dependsid"] ?? 0));
                $changed = true;
                break;
            case "comment_add":
                \mod_projectboard\comment_service::add($activity, $cm, $data);
                $changed = true;
                break;
            case "column_create":
            case "column_edit":
            case "column_archive":
            case "column_order":
            case "column_transition":
                \mod_projectboard\column_service::execute($activity, $cm, $action, $data);
                $changed = true;
                $taskid = 0;
                break;
            case "criterion_create":
                \mod_projectboard\criterion_service::create($activity, $cm, $data);
                $changed = true;
                break;
            case "criterion_score":
                \mod_projectboard\criterion_service::score($activity, $cm, $data);
                $changed = true;
                break;
            case "grade_save":
                \mod_projectboard\grading_service::save($activity, $cm, $data);
                $changed = true;
                break;
            default:
                throw new \invalid_parameter_exception("Unknown Project Board action");
        }
        if ($taskid) {
            $task = \mod_projectboard\access_manager::require_task($taskid, $activity, $cm);
            $boardid = (int)$task->boardid;
        }
        if (!$boardid) {
            $boards = \mod_projectboard\board_service::available($activity, $cm);
            $board = reset($boards);
            $boardid = $board ? (int)$board->id : 0;
        }
        $board = null;
        if ($boardid) {
            $board = \mod_projectboard\access_manager::require_board($boardid, $activity, $cm);
        }
        if ($changed && $board) {
            self::refresh_completion($activity, $cm, $board);
        }
        $html = $board ? $OUTPUT->render_from_template("mod_projectboard/board",
            \mod_projectboard\presentation\board_data::board($activity, $cm, $board)) : "";
        $dialog = "";
        if ($taskid && $DB->record_exists("projectboard_task", ["id" => $taskid, "archived" => 0])) {
            $task = \mod_projectboard\access_manager::require_task($taskid, $activity, $cm);
            $dialog = $OUTPUT->render_from_template("mod_projectboard/task",
                \mod_projectboard\presentation\board_data::task($activity, $cm, $task));
        }
        return ["html" => $html, "dialog" => $dialog, "taskid" => $taskid];
    }

    /**
     * Handles refresh completion operations for Project Board.
     */
    private static function refresh_completion(\stdClass $activity, \stdClass $cm, \stdClass $board): void {
        global $USER;
        if ($activity->workmode === "individual") {
            $users = [(int)$board->owneruserid];
        } else if ($activity->workmode === "groups") {
            $users = array_keys(groups_get_members((int)$board->groupid));
        } else {
            $users = array_keys(get_enrolled_users(\context_module::instance($cm->id),
                "mod/projectboard:view"));
        }
        foreach ($users as $userid) {
            if ($userid) {
                \mod_projectboard\completion_service::refresh($activity, $cm, (int)$userid);
            }
        }
    }

    /**
     * Handles execute returns operations for Project Board.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            "html" => new external_value(PARAM_RAW, "Updated Mustache board"),
            "dialog" => new external_value(PARAM_RAW, "Updated Mustache task details"),
            "taskid" => new external_value(PARAM_INT, "Selected task ID"),
        ]);
    }
}
