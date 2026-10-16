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
 * Moodle gradebook and project assessments.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

defined("MOODLE_INTERNAL") || die();

final class grading_service {
    public static function update_item(\stdClass $activity, $grades = null): void {
        global $CFG;
        require_once($CFG->libdir . "/gradelib.php");
        if ($activity->grademode === "none") {
            grade_update("mod/projectboard", $activity->course, "mod", "projectboard", $activity->id, 0,
                null, ["deleted" => 1]);
            return;
        }
        grade_update("mod/projectboard", $activity->course, "mod", "projectboard", $activity->id, 0,
            $grades, ["itemname" => $activity->name, "gradetype" => GRADE_TYPE_VALUE,
                "grademax" => $activity->grade, "grademin" => 0]);
    }

    public static function save(\stdClass $activity, \stdClass $cm, array $data): void {
        global $DB, $USER;
        access_manager::require_action("grade", $cm);
        if ($activity->grademode === "none") {
            throw new \moodle_exception("invalidaccess", "projectboard");
        }
        $board = access_manager::require_board((int)($data["boardid"] ?? 0), $activity, $cm, true);
        $score = (float)($data["grade"] ?? -1);
        if ($score < 0 || $score > $activity->grade) {
            throw new \invalid_parameter_exception("Grade is outside the configured range");
        }
        $users = [];
        if ($activity->workmode === "individual") {
            $users = [(int)$board->owneruserid];
        } else if ($activity->grademode === "project" && !empty($data["override"]) &&
                !empty($data["userid"])) {
            $userid = (int)$data["userid"];
            access_manager::require_assignee($userid, $board, $activity, $cm);
            $users = [$userid];
        } else if ($activity->workmode === "groups" && $activity->grademode === "project") {
            $users = array_keys(groups_get_members((int)$board->groupid));
        } else if ($activity->grademode === "project") {
            $users = array_keys(get_enrolled_users(access_manager::context($cm), "mod/projectboard:view"));
        } else {
            $userid = (int)($data["userid"] ?? 0);
            access_manager::require_assignee($userid, $board, $activity, $cm);
            $users = [$userid];
        }
        $grades = [];
        $feedback = clean_param($data["feedback"] ?? "", PARAM_TEXT);
        foreach ($users as $userid) {
            if (!is_enrolled(access_manager::context($cm), $userid, "mod/projectboard:view", true)) {
                continue;
            }
            $where = ["projectboardid" => $activity->id, "userid" => $userid];
            $row = $DB->get_record("projectboard_grade", $where);
            if (!$row) {
                $row = (object)($where + ["boardid" => $board->id, "grade" => $score,
                    "feedback" => $feedback, "feedbackformat" => FORMAT_PLAIN,
                    "gradedby" => $USER->id, "timecreated" => time(), "timemodified" => time()]);
                $DB->insert_record("projectboard_grade", $row);
            } else {
                $row->boardid = $board->id;
                $row->grade = $score;
                $row->feedback = $feedback;
                $row->gradedby = $USER->id;
                $row->timemodified = time();
                $DB->update_record("projectboard_grade", $row);
            }
            $grades[$userid] = (object)["userid" => $userid, "rawgrade" => $score,
                "feedback" => $feedback, "feedbackformat" => FORMAT_PLAIN,
                "dategraded" => time()];
        }
        self::update_item($activity, $grades);
    }

    public static function sync(\stdClass $activity, int $userid = 0): void {
        global $DB;
        $params = ["projectboardid" => $activity->id];
        if ($userid) {
            $params["userid"] = $userid;
        }
        $rows = $DB->get_records("projectboard_grade", $params);
        $grades = [];
        foreach ($rows as $grade) {
            $grades[$grade->userid] = (object)["userid" => $grade->userid, "rawgrade" => $grade->grade,
                "feedback" => $grade->feedback, "feedbackformat" => $grade->feedbackformat];
        }
        self::update_item($activity, $grades ?: null);
    }
}
