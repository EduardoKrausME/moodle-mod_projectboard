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
 * Complete user-aware backup structure.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Guarded Moodle class.
defined("MOODLE_INTERNAL") || die();

/**
 * Defines backup projectboard activity structure step functionality.
 */
class backup_projectboard_activity_structure_step extends backup_activity_structure_step {
    /**
     * Handles define structure for Project Board.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value("userinfo");
        $projectboard = new backup_nested_element("projectboard", ["id"], ["name",
            "intro",
            "introformat",
            "objective",
            "startdate",
            "enddate",
            "workmode",
            "allowcreate",
            "allowmove",
            "allowassign",
            "maxwip",
            "requireevidence",
            "grademode",
            "grade",
            "completioncreated",
            "completiondone",
            "completionproject",
            "completionchecklist",
            "completiongraded",
            "completionminscore",
            "showhistory",
            "notifications",
            "timecreated",
            "timemodified"]);
        $columns = new backup_nested_element("columns");
        $column = new backup_nested_element("column", ["id"], ["name",
            "colour",
            "sortorder",
            "wiplimit",
            "archived",
            "isdone",
            "isreview",
            "timecreated",
            "timemodified"]);
        $projectboard->add_child($columns);
        $columns->add_child($column);
        $transitions = new backup_nested_element("transitions");
        $transition = new backup_nested_element("transition", ["id"], ["fromcolumnid", "tocolumnid", "allowed"]);
        $projectboard->add_child($transitions);
        $transitions->add_child($transition);
        $boards = new backup_nested_element("boards");
        $board = new backup_nested_element("board", ["id"], ["owneruserid", "groupid", "name", "timecreated", "timemodified"]);
        $projectboard->add_child($boards);
        $boards->add_child($board);
        $tasks = new backup_nested_element("tasks");
        $task = new backup_nested_element("task", ["id"], ["columnid",
            "parentid",
            "title",
            "description",
            "descriptionformat",
            "createdby",
            "priority",
            "tags",
            "startdate",
            "duedate",
            "estimate",
            "sortorder",
            "archived",
            "version",
            "completedat",
            "timecreated",
            "timemodified"]);
        $board->add_child($tasks);
        $tasks->add_child($task);
        $assignees = new backup_nested_element("assignees");
        $assignee = new backup_nested_element("assignee", ["id"], ["userid"]);
        $task->add_child($assignees);
        $assignees->add_child($assignee);
        $checks = new backup_nested_element("checks");
        $check = new backup_nested_element("check", ["id"], ["title",
            "userid",
            "done",
            "required",
            "sortorder",
            "duedate",
            "timemodified"]);
        $task->add_child($checks);
        $checks->add_child($check);
        $deps = new backup_nested_element("deps");
        $dep = new backup_nested_element("dep", ["id"], ["dependsid"]);
        $task->add_child($deps);
        $deps->add_child($dep);
        $comments = new backup_nested_element("comments");
        $comment = new backup_nested_element("comment", ["id"], ["parentid",
            "userid",
            "content",
            "format",
            "timecreated",
            "timemodified"]);
        $task->add_child($comments);
        $comments->add_child($comment);
        $evidences = new backup_nested_element("evidences");
        $evidence = new backup_nested_element("evidence", ["id"], ["userid", "description", "timecreated"]);
        $task->add_child($evidences);
        $evidences->add_child($evidence);
        $histories = new backup_nested_element("histories");
        $history = new backup_nested_element("history", ["id"], ["userid", "action", "oldvalue", "newvalue", "timecreated"]);
        $task->add_child($histories);
        $histories->add_child($history);
        $notices = new backup_nested_element("notices");
        $notice = new backup_nested_element("notice", ["id"], ["userid", "eventname", "periodkey", "timecreated"]);
        $task->add_child($notices);
        $notices->add_child($notice);
        $grades = new backup_nested_element("grades");
        $grade = new backup_nested_element("grade", ["id"], ["boardid",
            "userid",
            "grade",
            "feedback",
            "feedbackformat",
            "gradedby",
            "timecreated",
            "timemodified"]);
        $projectboard->add_child($grades);
        $grades->add_child($grade);
        $criteria = new backup_nested_element("criteria");
        $criterion = new backup_nested_element("criterion", ["id"], ["name", "maxscore", "sortorder"]);
        $projectboard->add_child($criteria);
        $criteria->add_child($criterion);
        $scores = new backup_nested_element("scores");
        $score = new backup_nested_element("score", ["id"], ["userid", "boardid", "score", "gradedby", "timemodified"]);
        $criterion->add_child($scores);
        $scores->add_child($score);
        $projectboard->set_source_table("projectboard", ["id" => backup::VAR_ACTIVITYID]);
        $column->set_source_table("projectboard_column", ["projectboardid" => backup::VAR_PARENTID]);
        $transition->set_source_table("projectboard_transition", ["projectboardid" => backup::VAR_PARENTID]);
        if ($userinfo) {
            $board->set_source_table("projectboard_board", ["projectboardid" => backup::VAR_PARENTID]);
            $task->set_source_table("projectboard_task", ["boardid" => backup::VAR_PARENTID]);
            $assignee->set_source_table("projectboard_assignee", ["taskid" => backup::VAR_PARENTID]);
            $check->set_source_table("projectboard_check", ["taskid" => backup::VAR_PARENTID]);
            $dep->set_source_table("projectboard_dep", ["taskid" => backup::VAR_PARENTID]);
            $comment->set_source_table("projectboard_comment", ["taskid" => backup::VAR_PARENTID]);
            $evidence->set_source_table("projectboard_evidence", ["taskid" => backup::VAR_PARENTID]);
            $history->set_source_table("projectboard_history", ["taskid" => backup::VAR_PARENTID]);
            $notice->set_source_table("projectboard_notice", ["taskid" => backup::VAR_PARENTID]);
            $grade->set_source_table("projectboard_grade", ["projectboardid" => backup::VAR_PARENTID]);
            $criterion->set_source_table("projectboard_criterion", ["projectboardid" => backup::VAR_PARENTID]);
            $score->set_source_table("projectboard_score", ["criterionid" => backup::VAR_PARENTID]);
            $board->annotate_ids("user", "owneruserid");
            $board->annotate_ids("group", "groupid");
            $task->annotate_ids("user", "createdby");
            $assignee->annotate_ids("user", "userid");
            $check->annotate_ids("user", "userid");
            $comment->annotate_ids("user", "userid");
            $evidence->annotate_ids("user", "userid");
            $history->annotate_ids("user", "userid");
            $notice->annotate_ids("user", "userid");
            $grade->annotate_ids("user", "userid");
            $grade->annotate_ids("user", "gradedby");
            $score->annotate_ids("user", "userid");
            $score->annotate_ids("user", "gradedby");
            $evidence->annotate_files("mod_projectboard", "evidence", "id");
            $comment->annotate_files("mod_projectboard", "commentattachment", "id");
        }
        return $this->prepare_activity_structure($projectboard);
    }
}
