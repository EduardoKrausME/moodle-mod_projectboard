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
 * Teacher approval and correction workflow.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides review service functionality.
 */
final class review_service {
    /**
     * Handles submit for Project Board.
     */
    public static function submit(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_action("movetask", $cm);
        $reviewcolumn = $DB->get_record("projectboard_column",
            ["projectboardid" => $activity->id, "isreview" => 1, "archived" => 0]);
        if (!$reviewcolumn) {
            throw new \moodle_exception("invalidtransition", "projectboard");
        }
        task_service::move($activity, $cm, [
            "taskid" => $task->id, "columnid" => $reviewcolumn->id,
            "version" => (int)($data["version"] ?? 0), "position" => 0,
        ]);
        history_service::record((int)$task->id, "submitted_for_review");
    }

    /**
     * Handles evaluate for Project Board.
     */
    public static function evaluate(\stdClass $activity, \stdClass $cm,
            array $data, bool $approve): void {
        global $DB;
        access_manager::require_action("review", $cm);
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        $source = $DB->get_record("projectboard_column", ["id" => $task->columnid], "*", MUST_EXIST);
        if (!$source->isreview) {
            throw new \moodle_exception("invalidtransition", "projectboard");
        }
        $columns = board_service::columns($activity);
        $target = null;
        foreach ($columns as $column) {
            if ($approve && $column->isdone || !$approve && !$column->isdone && !$column->isreview &&
                    $column->sortorder > 0) {
                $target = $column;
                break;
            }
        }
        if (!$target) {
            throw new \moodle_exception("invalidtransition", "projectboard");
        }
        task_service::move($activity, $cm, [
            "taskid" => $task->id, "columnid" => $target->id,
            "version" => (int)($data["version"] ?? 0), "position" => 0,
        ]);
        $feedback = clean_param($data["feedback"] ?? "", PARAM_TEXT);
        history_service::record((int)$task->id, $approve ? "approved" : "returned_for_corrections",
            $source->id, ["column" => $target->id, "feedback" => $feedback]);
        notification_service::task_event($activity, $task, "review");
    }
}
