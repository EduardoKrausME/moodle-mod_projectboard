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
 * Criteria-based grade definition and scoring.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides criterion service functionality.
 */
final class criterion_service {
    /**
     * Handles all operations for Project Board.
     */
    public static function all(\stdClass $activity): array {
        global $DB;
        return array_values($DB->get_records("projectboard_criterion",
            ["projectboardid" => $activity->id], "sortorder ASC, id ASC"));
    }

    /**
     * Handles create operations for Project Board.
     */
    public static function create(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB;
        access_manager::require_action("grade", $cm);
        if ($activity->grademode !== "criteria") {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $name = trim(clean_param($data["name"] ?? "", PARAM_TEXT));
        $max = (float)($data["maxscore"] ?? 0);
        if (!$name || $max <= 0 || $max > 100000) {
            throw new \invalid_parameter_exception("Invalid grading criterion");
        }
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            $position = $DB->count_records("projectboard_criterion", ["projectboardid" => $activity->id]);
            $DB->insert_record("projectboard_criterion", (object)[
                "projectboardid" => $activity->id, "name" => $name,
                "maxscore" => $max, "sortorder" => $position,
            ]);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Handles score operations for Project Board.
     */
    public static function score(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB, $USER;
        access_manager::require_action("grade", $cm);
        if ($activity->grademode !== "criteria") {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $criterion = $DB->get_record("projectboard_criterion", [
            "id" => (int)($data["criterionid"] ?? 0), "projectboardid" => $activity->id,
        ], "*", MUST_EXIST);
        $board = access_manager::require_board((int)($data["boardid"] ?? 0), $activity, $cm, true);
        $userid = (int)($data["userid"] ?? 0);
        access_manager::require_assignee($userid, $board, $activity, $cm);
        $score = (float)($data["score"] ?? -1);
        if ($score < 0 || $score > $criterion->maxscore) {
            throw new \invalid_parameter_exception("Score is outside criterion range");
        }
        $lock = task_service::activity_lock((int)$activity->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            $where = ["criterionid" => $criterion->id, "userid" => $userid];
            $row = $DB->get_record("projectboard_score", $where);
            if (!$row) {
                $DB->insert_record("projectboard_score", (object)($where + [
                    "boardid" => $board->id, "score" => $score,
                    "gradedby" => $USER->id, "timemodified" => time(),
                ]));
            } else {
                $row->score = $score;
                $row->boardid = $board->id;
                $row->gradedby = $USER->id;
                $row->timemodified = time();
                $DB->update_record("projectboard_score", $row);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        $sql = "SELECT SUM(c.maxscore) AS possible, SUM(COALESCE(s.score, 0)) AS earned
                  FROM {projectboard_criterion} c
             LEFT JOIN {projectboard_score} s ON s.criterionid = c.id AND s.userid = :userid
                 WHERE c.projectboardid = :activityid";
        $totals = $DB->get_record_sql($sql, ["userid" => $userid, "activityid" => $activity->id]);
        $grade = $totals && $totals->possible > 0
            ? (float)$totals->earned / (float)$totals->possible * $activity->grade : 0;
        grading_service::save($activity, $cm, [
            "boardid" => $board->id, "userid" => $userid, "grade" => $grade,
            "feedback" => clean_param($data["feedback"] ?? "", PARAM_TEXT),
        ]);
    }
}
