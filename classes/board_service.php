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
 * Board provisioning and selection.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides board service functionality.
 */
final class board_service {
    /**
     * Handles default columns operations for Project Board.
     */
    public static function default_columns(int $activityid): void {
        global $DB;
        $items = [
            ["backlog", "#64748b", 0, 0],
            ["todo", "#3b82f6", 0, 0],
            ["inprogress", "#eab308", 0, 0],
            ["review", "#a855f7", 0, 1],
            ["done", "#16a34a", 1, 0],
        ];
        foreach ($items as $position => $item) {
            $DB->insert_record("projectboard_column", (object)[
                "projectboardid" => $activityid,
                "name" => get_string($item[0], "projectboard"),
                "colour" => $item[1],
                "sortorder" => $position,
                "wiplimit" => 0,
                "archived" => 0,
                "isdone" => $item[2],
                "isreview" => $item[3],
                "timecreated" => time(),
                "timemodified" => time(),
            ]);
        }
    }

    /**
     * Handles ensure operations for Project Board.
     */
    public static function ensure(\stdClass $activity, int $userid = 0, int $groupid = 0): \stdClass {
        global $DB;
        if ($activity->workmode === "individual" && !$userid ||
                $activity->workmode === "groups" && !$groupid) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        if ($activity->workmode === "collective") {
            $userid = 0;
            $groupid = 0;
        } else if ($activity->workmode === "individual") {
            $groupid = 0;
        } else {
            $userid = 0;
        }
        $where = ["projectboardid" => $activity->id, "owneruserid" => $userid, "groupid" => $groupid];
        if ($board = $DB->get_record("projectboard_board", $where)) {
            return $board;
        }
        $factory = \core\lock\lock_config::get_lock_factory("mod_projectboard");
        $lock = $factory->get_lock("newboard_" . $activity->id . "_" . $userid . "_" . $groupid, 15);
        if (!$lock) {
            throw new \moodle_exception("conflict", "projectboard");
        }
        try {
            if ($board = $DB->get_record("projectboard_board", $where)) {
                return $board;
            }
            $board = (object)($where + [
                "name" => $activity->name,
                "timecreated" => time(),
                "timemodified" => time(),
            ]);
            $board->id = $DB->insert_record("projectboard_board", $board);
            return $board;
        } finally {
            $lock->release();
        }
    }

    /**
     * Handles available operations for Project Board.
     */
    public static function available(\stdClass $activity, \stdClass $cm): array {
        global $DB, $USER;
        $boards = [];
        if ($activity->workmode === "collective") {
            $boards[] = self::ensure($activity);
        } else if ($activity->workmode === "groups") {
            $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
            foreach ($groups as $group) {
                $candidate = (object)["projectboardid" => $activity->id, "groupid" => $group->id, "owneruserid" => 0];
                if (!access_manager::can_access_board($candidate, $activity, $cm)) {
                    continue;
                }
                $board = self::ensure($activity, 0, (int)$group->id);
                $board->name = $group->name;
                $boards[] = $board;
            }
        } else {
            if (is_enrolled(access_manager::context($cm), $USER->id, "mod/projectboard:view", true)) {
                $boards[] = self::ensure($activity, (int)$USER->id);
            }
            if (has_capability("mod/projectboard:viewreports", access_manager::context($cm))) {
                $existing = $DB->get_records("projectboard_board", ["projectboardid" => $activity->id]);
                foreach ($existing as $board) {
                    if ($board->owneruserid != $USER->id &&
                            access_manager::can_access_board($board, $activity, $cm)) {
                        $owner = $DB->get_record("user", ["id" => $board->owneruserid], "id, firstname, lastname");
                        $board->name = $owner ? fullname($owner) : $activity->name;
                        $boards[] = $board;
                    }
                }
            }
        }
        return $boards;
    }

    /**
     * Handles columns operations for Project Board.
     */
    public static function columns(\stdClass $activity): array {
        global $DB;
        return array_values($DB->get_records("projectboard_column",
            ["projectboardid" => $activity->id, "archived" => 0], "sortorder ASC, id ASC"));
    }
}
