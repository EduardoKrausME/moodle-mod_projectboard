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
 * Transactional Kanban task commands.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

defined("MOODLE_INTERNAL") || die();

final class task_service {
    private static function lock(int $boardid): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory("mod_projectboard");
        $lock = $factory->get_lock("board_" . $boardid, 15);
        if (!$lock) {
            throw new \moodle_exception("conflict", "projectboard");
        }
        return $lock;
    }

    private static function column(\stdClass $activity, int $columnid): \stdClass {
        global $DB;
        $column = $DB->get_record("projectboard_column", [
            "id" => $columnid, "projectboardid" => $activity->id, "archived" => 0
        ], "*", MUST_EXIST);
        return $column;
    }

    private static function require_version(\stdClass $task, array $data): void {
        if (!array_key_exists("version", $data) || (int)$data["version"] !== (int)$task->version) {
            throw new \moodle_exception("conflict", "projectboard");
        }
    }

    private static function assert_capacity(\stdClass $activity, \stdClass $column, int $boardid, int $excludedid = 0): void {
        global $DB;
        $limit = (int)$column->wiplimit ?: (int)$activity->maxwip;
        if ($limit <= 0 || $column->isdone) {
            return;
        }
        $sql = "SELECT COUNT(1) FROM {projectboard_task}
                 WHERE boardid = :boardid AND columnid = :columnid AND archived = 0 AND id <> :excludedid";
        $count = $DB->count_records_sql($sql, [
            "boardid" => $boardid, "columnid" => $column->id, "excludedid" => $excludedid
        ]);
        if ($count >= $limit) {
            throw new \moodle_exception("tasklimit", "projectboard");
        }
    }

    private static function assert_done(\stdClass $activity, \stdClass $task): void {
        global $DB;
        if (dependency_service::blockers((int)$task->id)) {
            throw new \moodle_exception("blocked", "projectboard");
        }
        if ($activity->requireevidence && !$DB->record_exists("projectboard_evidence", ["taskid" => $task->id])) {
            throw new \moodle_exception("requiresfiles", "projectboard");
        }
        if ($DB->record_exists_select("projectboard_check", "taskid = ? AND required = 1 AND done = 0", [$task->id])) {
            throw new \moodle_exception("blocked", "projectboard");
        }
        $sql = "SELECT COUNT(1) FROM {projectboard_task} t
                  JOIN {projectboard_column} c ON c.id = t.columnid
                 WHERE t.parentid = ? AND t.archived = 0 AND c.isdone = 0";
        if ($DB->count_records_sql($sql, [$task->id])) {
            throw new \moodle_exception("blocked", "projectboard");
        }
    }

    private static function reorder(int $boardid, int $columnid): void {
        global $DB;
        $tasks = $DB->get_records("projectboard_task",
            ["boardid" => $boardid, "columnid" => $columnid, "archived" => 0],
            "sortorder ASC, id ASC", "id, sortorder");
        $i = 0;
        foreach ($tasks as $task) {
            if ((int)$task->sortorder !== $i) {
                $DB->set_field("projectboard_task", "sortorder", $i, ["id" => $task->id]);
            }
            $i++;
        }
    }

    public static function create(\stdClass $activity, \stdClass $cm, array $data): int {
        global $DB, $USER;
        $board = access_manager::require_board((int)($data["boardid"] ?? 0), $activity, $cm, true);
        access_manager::require_action("addtask", $cm);
        if (!$activity->allowcreate && !access_manager::can_manage($cm)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $title = trim(clean_param($data["title"] ?? "", PARAM_TEXT));
        if (!$title) {
            throw new \invalid_parameter_exception("A task title is required");
        }
        $column = self::column($activity, (int)($data["columnid"] ?? 0));
        $parentid = (int)($data["parentid"] ?? 0);
        if ($parentid) {
            $parent = access_manager::require_task($parentid, $activity, $cm, true);
            if ((int)$parent->boardid !== (int)$board->id) {
                throw new \moodle_exception("invalidaccess", "projectboard");
            }
        }
        $lock = self::lock((int)$board->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            self::assert_capacity($activity, $column, (int)$board->id);
            $position = (int)$DB->get_field_sql(
                "SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {projectboard_task}
                  WHERE boardid = ? AND columnid = ? AND archived = 0", [$board->id, $column->id]);
            $task = (object)[
                "boardid" => $board->id, "columnid" => $column->id, "parentid" => $parentid,
                "title" => $title, "description" => clean_param($data["description"] ?? "", PARAM_TEXT),
                "descriptionformat" => FORMAT_PLAIN, "createdby" => $USER->id,
                "priority" => "normal", "tags" => "",
                "startdate" => 0, "duedate" => 0, "estimate" => 0,
                "sortorder" => $position, "archived" => 0, "version" => 1,
                "completedat" => 0, "timecreated" => time(), "timemodified" => time()
            ];
            $task->id = $DB->insert_record("projectboard_task", $task);
            history_service::record((int)$task->id, "created", null, ["title" => $title]);
            $transaction->allow_commit();
            return (int)$task->id;
        } finally {
            $lock->release();
        }
    }

    public static function update(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_edit($task, $cm);
        $lock = self::lock((int)$task->boardid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $task = access_manager::require_task((int)$task->id, $activity, $cm, true);
            self::require_version($task, $data);
            $before = clone $task;
            foreach (["title", "description", "priority", "tags", "startdate", "duedate", "estimate"] as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }
                if (in_array($field, ["startdate", "duedate", "estimate"], true)) {
                    $task->$field = max(0, (int)$data[$field]);
                } else if ($field === "description") {
                    $task->$field = clean_param($data[$field], PARAM_TEXT);
                } else if ($field === "priority") {
                    $task->$field = in_array($data[$field], ["low", "normal", "high", "urgent"], true)
                        ? $data[$field] : "normal";
                } else {
                    $task->$field = clean_param($data[$field], PARAM_TEXT);
                }
            }
            if (!trim($task->title)) {
                throw new \invalid_parameter_exception("A title is required");
            }
            if ($task->duedate && $task->startdate && $task->startdate > $task->duedate) {
                throw new \moodle_exception("dateinvalid", "projectboard");
            }
            $task->version++;
            $task->timemodified = time();
            $DB->update_record("projectboard_task", $task);
            foreach (["title", "description", "priority", "tags", "startdate", "duedate", "estimate"] as $field) {
                if ($before->$field != $task->$field) {
                    history_service::record((int)$task->id, $field . "_changed", $before->$field, $task->$field);
                }
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    public static function assign(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB, $USER;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_action("assign", $cm);
        if (!$activity->allowassign && !access_manager::can_manage($cm)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $board = access_manager::require_board((int)$task->boardid, $activity, $cm, true);
        $raw = $data["userids"] ?? [];
        $userids = array_values(array_unique(array_filter(array_map("intval", is_array($raw) ? $raw : []))));
        foreach ($userids as $userid) {
            access_manager::require_assignee($userid, $board, $activity, $cm);
        }
        $transaction = $DB->start_delegated_transaction();
        $existing = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
        $DB->delete_records("projectboard_assignee", ["taskid" => $task->id]);
        foreach ($userids as $userid) {
            $DB->insert_record("projectboard_assignee", (object)["taskid" => $task->id, "userid" => $userid]);
        }
        history_service::record((int)$task->id, "assignees_changed", $existing, $userids);
        $DB->set_field("projectboard_task", "version", $task->version + 1, ["id" => $task->id]);
        $transaction->allow_commit();
        foreach (array_diff($userids, array_map("intval", $existing)) as $userid) {
            notification_service::send($activity, $task, (int)$userid, "assignment", "assigned");
        }
    }

    public static function move(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_action("movetask", $cm);
        if (!$activity->allowmove && !access_manager::can_manage($cm)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $target = self::column($activity, (int)($data["columnid"] ?? 0));
        $lock = self::lock((int)$task->boardid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $task = access_manager::require_task((int)$task->id, $activity, $cm, true);
            self::require_version($task, $data);
            $from = (int)$task->columnid;
            if ($from !== (int)$target->id) {
                $rule = $DB->get_record("projectboard_transition",
                    ["fromcolumnid" => $from, "tocolumnid" => $target->id]);
                if ($rule && !$rule->allowed) {
                    throw new \moodle_exception("invalidtransition", "projectboard");
                }
                self::assert_capacity($activity, $target, (int)$task->boardid);
            }
            if ($target->isdone) {
                self::assert_done($activity, $task);
            }
            $task->columnid = $target->id;
            $task->sortorder = 2147483647;
            $task->completedat = $target->isdone ? time() : 0;
            $task->version++;
            $task->timemodified = time();
            $DB->update_record("projectboard_task", $task);
            self::reorder((int)$task->boardid, $from);
            self::reorder((int)$task->boardid, (int)$target->id);
            $position = max(0, (int)($data["position"] ?? 0));
            $ordered = array_values($DB->get_records("projectboard_task", [
                "boardid" => $task->boardid, "columnid" => $target->id, "archived" => 0
            ], "sortorder ASC, id ASC", "id"));
            $ordered = array_values(array_filter($ordered, static function($row) use ($task) {
                return (int)$row->id !== (int)$task->id;
            }));
            array_splice($ordered, min($position, count($ordered)), 0, [$task]);
            foreach ($ordered as $index => $row) {
                $DB->set_field("projectboard_task", "sortorder", $index, ["id" => $row->id]);
            }
            history_service::record((int)$task->id, "moved", $from, $target->id);
            if ($target->isdone) {
                history_service::record((int)$task->id, "completed", null, $task->completedat);
            } else if ($from !== (int)$target->id) {
                history_service::record((int)$task->id, "reopened", $from, $target->id);
            }
            $transaction->allow_commit();
            if ($target->isreview) {
                notification_service::task_event($activity, $task, "review");
            }
        } finally {
            $lock->release();
        }
    }

    public static function archive(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_edit($task, $cm);
        self::require_version($task, $data);
        if ($DB->record_exists("projectboard_task", ["parentid" => $task->id, "archived" => 0])) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $task->archived = 1;
        $task->version++;
        $task->timemodified = time();
        $DB->update_record("projectboard_task", $task);
        history_service::record((int)$task->id, "archived");
    }

    public static function duplicate(\stdClass $activity, \stdClass $cm, array $data): int {
        $original = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm);
        access_manager::require_action("addtask", $cm);
        return self::create($activity, $cm, [
            "boardid" => $original->boardid, "columnid" => $original->columnid,
            "title" => $original->title . " (copy)", "description" => $original->description
        ]);
    }
}
