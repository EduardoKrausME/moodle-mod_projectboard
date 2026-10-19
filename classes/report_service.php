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
 * Teacher analytics and authorized CSV export.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides report service functionality.
 */
final class report_service {
    /**
     * Handles rows for Project Board.
     */
    public static function rows(\stdClass $activity, \stdClass $cm): array {
        global $DB;
        access_manager::require_action("viewreports", $cm);
        $rows = [];
        $boards = board_service::available($activity, $cm);
        foreach ($boards as $board) {
            if (!access_manager::can_access_board($board, $activity, $cm)) {
                continue;
            }
            $tasks = $DB->get_records("projectboard_task", ["boardid" => $board->id, "archived" => 0]);
            foreach ($tasks as $task) {
                $column = $DB->get_record("projectboard_column", ["id" => $task->columnid], "id,name,isdone");
                $users = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
                $rows[] = [
                    "board" => $board->name,
                    "taskid" => $task->id,
                    "title" => $task->title,
                    "status" => $column ? $column->name : "",
                    "priority" => $task->priority,
                    "duedate" => $task->duedate ? userdate($task->duedate, "%Y-%m-%d %H:%M") : "",
                    "assignees" => implode(", ", $users),
                    "overdue" => $task->duedate && $task->duedate < time() && (!$column || !$column->isdone)
                        ? get_string("late", "projectboard") : "",
                ];
            }
        }
        return $rows;
    }
}
