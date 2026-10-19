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
 * Per-student Moodle completion rules.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides completion service functionality.
 */
final class completion_service {
    /**
     * Handles completed operations for Project Board.
     */
    public static function completed(\stdClass $activity, \stdClass $cm, int $userid): bool {
        global $DB;
        if (!$activity->completioncreated && !$activity->completiondone && !$activity->completionproject &&
                !$activity->completionchecklist && !$activity->completiongraded && !$activity->completionminscore) {
            return false;
        }
        $sql = "SELECT t.id, t.createdby, t.columnid, b.owneruserid, b.groupid, b.id AS boardid
                  FROM {projectboard_task} t
                  JOIN {projectboard_board} b ON b.id = t.boardid
                 WHERE b.projectboardid = :activityid AND t.archived = 0";
        $tasks = $DB->get_records_sql($sql, ["activityid" => $activity->id]);
        $created = 0;
        $done = 0;
        $allrequired = true;
        $hascompletedproject = false;
        foreach ($tasks as $task) {
            if (!self::belongs($task, $activity, $userid)) {
                continue;
            }
            if ((int)$task->createdby === $userid) {
                $created++;
            }
            if ($DB->get_field("projectboard_column", "isdone", ["id" => $task->columnid])) {
                $done++;
                $hascompletedproject = true;
            }
            if ($activity->completionchecklist &&
                    $DB->record_exists_select("projectboard_check",
                        "taskid = ? AND required = 1 AND done = 0", [$task->id])) {
                $allrequired = false;
            }
        }
        if ($activity->completioncreated && $created < $activity->completioncreated ||
                $activity->completiondone && $done < $activity->completiondone ||
                $activity->completionproject && !$hascompletedproject ||
                $activity->completionchecklist && !$allrequired) {
            return false;
        }
        if ($activity->completiongraded || $activity->completionminscore) {
            $grade = $DB->get_record("projectboard_grade",
                ["projectboardid" => $activity->id, "userid" => $userid]);
            if (!$grade || $activity->completionminscore && $grade->grade < $activity->completionminscore) {
                return false;
            }
        }
        return true;
    }

    /**
     * Handles belongs operations for Project Board.
     */
    private static function belongs(\stdClass $task, \stdClass $activity, int $userid): bool {
        if ($activity->workmode === "individual") {
            return (int)$task->owneruserid === $userid;
        }
        if ($activity->workmode === "groups") {
            return groups_is_member((int)$task->groupid, $userid);
        }
        return true;
    }

    /**
     * Handles refresh operations for Project Board.
     */
    public static function refresh(\stdClass $activity, \stdClass $cm, int $userid): void {
        global $DB;
        $course = $DB->get_record("course", ["id" => $activity->course], "*", MUST_EXIST);
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
