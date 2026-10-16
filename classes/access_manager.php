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
 * Board-scoped access controls.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

defined("MOODLE_INTERNAL") || die();

final class access_manager {
    public static function context(\stdClass $cm): \context_module {
        return \context_module::instance($cm->id);
    }

    public static function require_view(\stdClass $cm): void {
        require_login($cm->course, false, $cm);
        require_capability("mod/projectboard:view", self::context($cm));
    }

    public static function can_manage(\stdClass $cm): bool {
        return has_capability("mod/projectboard:manage", self::context($cm));
    }

    public static function can_access_board(\stdClass $board, \stdClass $activity, \stdClass $cm, bool $write = false): bool {
        global $USER;
        $context = self::context($cm);
        if ((int)$board->projectboardid !== (int)$activity->id || !has_capability("mod/projectboard:view", $context)) {
            return false;
        }
        $staff = has_capability("mod/projectboard:manage", $context) ||
            has_capability("mod/projectboard:viewreports", $context);
        if ($activity->workmode === "collective") {
            return true;
        }
        if ($activity->workmode === "individual") {
            return (int)$board->owneruserid === (int)$USER->id || $staff && self::may_view_user((int)$board->owneruserid, $cm);
        }
        if (!$board->groupid) {
            return false;
        }
        if (groups_is_member((int)$board->groupid, $USER->id)) {
            return true;
        }
        if (has_capability("moodle/site:accessallgroups", $context)) {
            return true;
        }
        if (!$write && groups_get_activity_groupmode($cm) == VISIBLEGROUPS) {
            return true;
        }
        return false;
    }

    private static function may_view_user(int $userid, \stdClass $cm): bool {
        global $USER;
        $context = self::context($cm);
        if (has_capability("moodle/site:accessallgroups", $context) ||
                groups_get_activity_groupmode($cm) != SEPARATEGROUPS) {
            return true;
        }
        $mygroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
        $theirgroups = groups_get_all_groups($cm->course, $userid, $cm->groupingid);
        return (bool)array_intersect(array_keys($mygroups), array_keys($theirgroups));
    }

    public static function require_board(int $boardid, \stdClass $activity, \stdClass $cm, bool $write = false): \stdClass {
        global $DB;
        $board = $DB->get_record("projectboard_board", ["id" => $boardid], MUST_EXIST);
        if (!self::can_access_board($board, $activity, $cm, $write)) {
            throw new \required_capability_exception(self::context($cm), "mod/projectboard:view", CAP_ALLOW, "nopermissions");
        }
        return $board;
    }

    public static function require_action(string $capability, \stdClass $cm): void {
        require_capability("mod/projectboard:" . $capability, self::context($cm));
    }

    public static function may_edit(\stdClass $task, \stdClass $cm): bool {
        global $USER;
        $context = self::context($cm);
        return has_capability("mod/projectboard:editall", $context) ||
            ((int)$task->createdby === (int)$USER->id && has_capability("mod/projectboard:editown", $context));
    }

    public static function require_edit(\stdClass $task, \stdClass $cm): void {
        if (!self::may_edit($task, $cm)) {
            throw new \required_capability_exception(self::context($cm), "mod/projectboard:editown", CAP_ALLOW, "nopermissions");
        }
    }

    public static function require_task(int $taskid, \stdClass $activity, \stdClass $cm, bool $write = false): \stdClass {
        global $DB;
        $task = $DB->get_record("projectboard_task", ["id" => $taskid], MUST_EXIST);
        self::require_board((int)$task->boardid, $activity, $cm, $write);
        if ($task->archived && $write && !self::can_manage($cm)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        return $task;
    }

    public static function require_assignee(int $userid, \stdClass $board, \stdClass $activity, \stdClass $cm): void {
        global $DB;
        $context = self::context($cm);
        if (!is_enrolled($context, $userid, "mod/projectboard:view", true)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        if ($activity->workmode === "individual" && (int)$board->owneruserid !== $userid) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        if ($activity->workmode === "groups" && !groups_is_member((int)$board->groupid, $userid)) {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
    }
}
