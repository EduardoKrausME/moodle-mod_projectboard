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
 * Column management and transition policy.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

defined("MOODLE_INTERNAL") || die();

final class column_service {
    public static function execute(\stdClass $activity, \stdClass $cm, string $action, array $data): void {
        global $DB;
        access_manager::require_action("managecolumns", $cm);
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            if ($action === "column_create") {
            $name = trim(clean_param($data["name"] ?? "", PARAM_TEXT));
            if (!$name) {
                throw new \invalid_parameter_exception("Column name required");
            }
            $colour = self::colour($data["colour"] ?? "#64748b");
            $next = (int)$DB->get_field_sql("SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {projectboard_column} WHERE projectboardid = ?",
                [$activity->id]);
            $DB->insert_record("projectboard_column", (object)[
                "projectboardid" => $activity->id, "name" => $name, "colour" => $colour,
                "sortorder" => $next, "wiplimit" => max(0, (int)($data["wiplimit"] ?? 0)),
                "archived" => 0, "isdone" => 0, "isreview" => 0,
                "timecreated" => time(), "timemodified" => time()
            ]);
        } else {
            $column = $DB->get_record("projectboard_column", [
                "id" => (int)($data["columnid"] ?? 0), "projectboardid" => $activity->id
            ], "*", MUST_EXIST);
            if ($action === "column_edit") {
                $column->name = trim(clean_param($data["name"] ?? $column->name, PARAM_TEXT));
                if (!$column->name) {
                    throw new \invalid_parameter_exception("Column name required");
                }
                $column->colour = self::colour($data["colour"] ?? $column->colour);
                $column->wiplimit = max(0, (int)($data["wiplimit"] ?? $column->wiplimit));
                $column->timemodified = time();
                $DB->update_record("projectboard_column", $column);
            } else if ($action === "column_archive") {
                if ($DB->record_exists("projectboard_task", ["columnid" => $column->id, "archived" => 0])) {
                    throw new \moodle_exception("invalidaccess", "projectboard");
                }
                $column->archived = 1;
                $DB->update_record("projectboard_column", $column);
            } else if ($action === "column_order") {
                $columns = board_service::columns($activity);
                $ids = array_column($columns, "id");
                $current = array_search((int)$column->id, $ids, true);
                $next = $current + ((int)($data["direction"] ?? 0) > 0 ? 1 : -1);
                if (isset($ids[$next])) {
                    [$ids[$current], $ids[$next]] = [$ids[$next], $ids[$current]];
                    foreach ($ids as $position => $id) {
                        $DB->set_field("projectboard_column", "sortorder", $position, ["id" => $id]);
                    }
                }
            } else if ($action === "column_transition") {
                $target = $DB->get_record("projectboard_column", [
                    "id" => (int)($data["targetid"] ?? 0), "projectboardid" => $activity->id
                ], "*", MUST_EXIST);
                $where = ["fromcolumnid" => $column->id, "tocolumnid" => $target->id];
                $rule = $DB->get_record("projectboard_transition", $where);
                if (!$rule) {
                    $DB->insert_record("projectboard_transition", (object)($where + [
                        "projectboardid" => $activity->id, "allowed" => !empty($data["allowed"]) ? 1 : 0
                    ]));
                } else {
                    $rule->allowed = !empty($data["allowed"]) ? 1 : 0;
                    $DB->update_record("projectboard_transition", $rule);
                }
            } else {
                throw new \invalid_parameter_exception("Invalid column operation");
            }
        }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    private static function colour(string $colour): string {
        return preg_match("/^#[0-9a-fA-F]{6}$/", $colour) ? $colour : "#64748b";
    }
}
