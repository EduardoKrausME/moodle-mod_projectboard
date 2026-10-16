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
 * Dependency graph validation.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

defined("MOODLE_INTERNAL") || die();

final class dependency_service {
    public static function add(\stdClass $activity, \stdClass $cm, int $taskid, int $dependsid): void {
        global $DB;
        $task = access_manager::require_task($taskid, $activity, $cm, true);
        access_manager::require_edit($task, $cm);
        $other = access_manager::require_task($dependsid, $activity, $cm);
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            if ($taskid === $dependsid || (int)$task->boardid !== (int)$other->boardid ||
                    self::reaches($dependsid, $taskid, [])) {
                throw new \moodle_exception("cyclicdependency", "projectboard");
            }
            if (!$DB->record_exists("projectboard_dep", ["taskid" => $taskid, "dependsid" => $dependsid])) {
                $DB->insert_record("projectboard_dep", (object)["taskid" => $taskid, "dependsid" => $dependsid]);
                history_service::record($taskid, "dependency_added", null, $dependsid);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    public static function remove(\stdClass $activity, \stdClass $cm, int $taskid, int $dependsid): void {
        global $DB;
        $task = access_manager::require_task($taskid, $activity, $cm, true);
        access_manager::require_edit($task, $cm);
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $DB->delete_records("projectboard_dep", ["taskid" => $taskid, "dependsid" => $dependsid]);
            history_service::record($taskid, "dependency_removed", $dependsid);
        } finally {
            $lock->release();
        }
    }

    private static function reaches(int $current, int $target, array $visited): bool {
        global $DB;
        if ($current === $target) {
            return true;
        }
        if (isset($visited[$current])) {
            return false;
        }
        $visited[$current] = true;
        $children = $DB->get_fieldset_select("projectboard_dep", "dependsid", "taskid = ?", [$current]);
        foreach ($children as $child) {
            if (self::reaches((int)$child, $target, $visited)) {
                return true;
            }
        }
        return false;
    }

    public static function blockers(int $taskid): array {
        global $DB;
        $sql = "SELECT t.id, t.title FROM {projectboard_dep} d
                  JOIN {projectboard_task} t ON t.id = d.dependsid
                  JOIN {projectboard_column} c ON c.id = t.columnid
                 WHERE d.taskid = :taskid AND (c.isdone = 0 OR t.archived = 1)";
        return array_values($DB->get_records_sql($sql, ["taskid" => $taskid]));
    }
}
