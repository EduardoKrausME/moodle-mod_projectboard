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
 * Task checklist management.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides checklist service functionality.
 */
final class checklist_service {
    /**
     * Handles execute operations for Project Board.
     */
    public static function execute(\stdClass $activity, \stdClass $cm, string $action, array $data): void {
        global $DB;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_edit($task, $cm);
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            if ($action === "check_add") {
                $title = trim(clean_param($data["title"] ?? "", PARAM_TEXT));
                if (!$title) {
                    throw new \invalid_parameter_exception("Checklist title required");
                }
                $max = (int)$DB->get_field_sql(
                    "SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {projectboard_check} WHERE taskid = ?",
                    [$task->id]
                );
                $userid = (int)($data["userid"] ?? 0);
                if ($userid) {
                    $board = $DB->get_record("projectboard_board", ["id" => $task->boardid], "*", MUST_EXIST);
                    access_manager::require_assignee($userid, $board, $activity, $cm);
                }
                $id = $DB->insert_record("projectboard_check", (object)[
                    "taskid" => $task->id, "title" => $title, "userid" => $userid,
                    "done" => 0, "required" => 1, "sortorder" => $max,
                    "duedate" => max(0, (int)($data["duedate"] ?? 0)), "timemodified" => time(),
                ]);
                history_service::record((int)$task->id, "check_added", null, $id);
            } else {
                $item = $DB->get_record("projectboard_check", [
                    "id" => (int)($data["itemid"] ?? 0), "taskid" => $task->id,
                ], "*", MUST_EXIST);
                if ($action === "check_toggle") {
                    $item->done = !empty($data["done"]) ? 1 : 0;
                } else if ($action === "check_delete") {
                    $DB->delete_records("projectboard_check", ["id" => $item->id]);
                    history_service::record((int)$task->id, "check_deleted", $item->id);
                    $transaction->allow_commit();
                    return;
                } else if ($action === "check_order") {
                    $items = array_values($DB->get_records("projectboard_check", ["taskid" => $task->id], "sortorder ASC, id ASC"));
                    $ids = array_map(static function($row) {
                        return (int)$row->id;
                    }, $items);
                    $from = array_search((int)$item->id, $ids, true);
                    $to = $from + (!empty($data["direction"]) && $data["direction"] > 0 ? 1 : -1);
                    if (isset($ids[$to])) {
                        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
                        foreach ($ids as $index => $id) {
                            $DB->set_field("projectboard_check", "sortorder", $index, ["id" => $id]);
                        }
                    }
                } else {
                    throw new \invalid_parameter_exception("Invalid checklist operation");
                }
                $item->timemodified = time();
                $DB->update_record("projectboard_check", $item);
                history_service::record((int)$task->id, $action, null, $item->id);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Handles progress operations for Project Board.
     */
    public static function progress(int $taskid): array {
        global $DB;
        $items = array_values($DB->get_records("projectboard_check", ["taskid" => $taskid], "sortorder ASC, id ASC"));
        $done = count(array_filter($items, static function($item) {
            return $item->done;
        }));
        return ["items" => $items, "done" => $done, "total" => count($items),
            "percent" => $items ? (int)round($done / count($items) * 100) : 0];
    }
}
