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
 * Activity restore with user, group and task mapping.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

class restore_projectboard_activity_structure_step extends restore_activity_structure_step {
    private $pendingdeps = [];
    private $pendingparents = [];
    private $pendingcomments = [];

    protected function define_structure() {
        $paths = [
            new restore_path_element("projectboard", "/activity/projectboard"),
            new restore_path_element("column", "/activity/projectboard/columns/column"),
            new restore_path_element("transition", "/activity/projectboard/transitions/transition"),
            new restore_path_element("board", "/activity/projectboard/boards/board"),
            new restore_path_element("task", "/activity/projectboard/boards/board/tasks/task"),
            new restore_path_element("assignee", "/activity/projectboard/boards/board/tasks/task/assignees/assignee"),
            new restore_path_element("check", "/activity/projectboard/boards/board/tasks/task/checks/check"),
            new restore_path_element("dep", "/activity/projectboard/boards/board/tasks/task/deps/dep"),
            new restore_path_element("comment", "/activity/projectboard/boards/board/tasks/task/comments/comment"),
            new restore_path_element("evidence", "/activity/projectboard/boards/board/tasks/task/evidences/evidence"),
            new restore_path_element("history", "/activity/projectboard/boards/board/tasks/task/histories/history"),
            new restore_path_element("notice", "/activity/projectboard/boards/board/tasks/task/notices/notice"),
            new restore_path_element("grade", "/activity/projectboard/grades/grade"),
            new restore_path_element("criterion", "/activity/projectboard/criteria/criterion"),
            new restore_path_element("score", "/activity/projectboard/criteria/criterion/scores/score")
        ];
        return $this->prepare_activity_structure($paths);
    }

    protected function process_projectboard($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $oldid = $data->id;
        $data->id = $DB->insert_record("projectboard", $data);
        $this->apply_activity_instance($data->id);
    }

    protected function process_column($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->projectboardid = $this->get_new_parentid("projectboard");
        $data->id = $DB->insert_record("projectboard_column", $data);
        $this->set_mapping("column", $oldid, $data->id);
    }

    protected function process_transition($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->projectboardid = $this->get_new_parentid("projectboard");
        $data->fromcolumnid = $this->get_mappingid("column", (int)$data->fromcolumnid, 0);
        $data->tocolumnid = $this->get_mappingid("column", (int)$data->tocolumnid, 0);
        if (!$data->fromcolumnid || !$data->tocolumnid) {
            return;
        }
        $data->id = $DB->insert_record("projectboard_transition", $data);
        $this->set_mapping("transition", $oldid, $data->id);
    }

    protected function process_board($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->projectboardid = $this->get_new_parentid("projectboard");
        $data->owneruserid = $this->get_mappingid("user", (int)$data->owneruserid, 0);
        $data->groupid = $this->get_mappingid("group", (int)$data->groupid, 0);
        $data->id = $DB->insert_record("projectboard_board", $data);
        $this->set_mapping("board", $oldid, $data->id);
    }

    protected function process_task($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->boardid = $this->get_new_parentid("board");
        $data->columnid = $this->get_mappingid("column", (int)$data->columnid, 0);
        $oldparent = (int)$data->parentid;
        $data->parentid = 0;
        $data->createdby = $this->get_mappingid("user", (int)$data->createdby, 0);
        $data->id = $DB->insert_record("projectboard_task", $data);
        if ($oldparent) {
            $this->pendingparents[] = [(int)$data->id, $oldparent];
        }
        $this->set_mapping("task", $oldid, $data->id);
    }

    protected function process_assignee($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid("task");
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->id = $DB->insert_record("projectboard_assignee", $data);
        $this->set_mapping("assignee", $oldid, $data->id);
    }

    protected function process_check($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid("task");
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->id = $DB->insert_record("projectboard_check", $data);
        $this->set_mapping("check", $oldid, $data->id);
    }

    protected function process_dep($data) {
        $data = (object)$data;
        $this->pendingdeps[] = [(int)$this->get_new_parentid("task"), (int)$data->dependsid];
    }

    protected function process_comment($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid("task");
        $oldparent = (int)$data->parentid;
        $data->parentid = 0;
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->id = $DB->insert_record("projectboard_comment", $data);
        if ($oldparent) {
            $this->pendingcomments[] = [(int)$data->id, $oldparent];
        }
        $this->set_mapping("comment", $oldid, $data->id, true);
    }

    protected function process_evidence($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid("task");
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->id = $DB->insert_record("projectboard_evidence", $data);
        $this->set_mapping("evidence", $oldid, $data->id, true);
    }

    protected function process_history($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->taskid = $this->get_new_parentid("task");
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->id = $DB->insert_record("projectboard_history", $data);
        $this->set_mapping("history", $oldid, $data->id);
    }

    protected function process_notice($data) {
        // Delivery history is not restored to avoid masking future notifications.
    }

    protected function process_grade($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->projectboardid = $this->get_new_parentid("projectboard");
        $data->boardid = $this->get_mappingid("board", (int)$data->boardid, 0);
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->gradedby = $this->get_mappingid("user", (int)$data->gradedby, 0);
        $data->id = $DB->insert_record("projectboard_grade", $data);
        $this->set_mapping("grade", $oldid, $data->id);
    }

    protected function process_criterion($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->projectboardid = $this->get_new_parentid("projectboard");
        $data->id = $DB->insert_record("projectboard_criterion", $data);
        $this->set_mapping("criterion", $oldid, $data->id);
    }

    protected function process_score($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->criterionid = $this->get_new_parentid("criterion");
        $data->userid = $this->get_mappingid("user", (int)$data->userid, 0);
        $data->boardid = $this->get_mappingid("board", (int)$data->boardid, 0);
        $data->gradedby = $this->get_mappingid("user", (int)$data->gradedby, 0);
        $data->id = $DB->insert_record("projectboard_score", $data);
        $this->set_mapping("score", $oldid, $data->id);
    }

    protected function after_execute() {
        global $DB;
        foreach ($this->pendingparents as [$childid, $oldparentid]) {
            $parentid = $this->get_mappingid("task", $oldparentid, 0);
            if ($parentid) {
                $DB->set_field("projectboard_task", "parentid", $parentid, ["id" => $childid]);
            }
        }
        foreach ($this->pendingcomments as [$commentid, $oldparentid]) {
            $parentid = $this->get_mappingid("comment", $oldparentid, 0);
            if ($parentid) {
                $DB->set_field("projectboard_comment", "parentid", $parentid, ["id" => $commentid]);
            }
        }
        foreach ($this->pendingdeps as [$taskid, $olddependsid]) {
            $dependsid = $this->get_mappingid("task", $olddependsid, 0);
            if ($taskid && $dependsid && $taskid !== $dependsid) {
                $DB->insert_record("projectboard_dep", (object)[
                    "taskid" => $taskid, "dependsid" => $dependsid
                ]);
            }
        }
        $this->add_related_files("mod_projectboard", "evidence", "evidence");
        $this->add_related_files("mod_projectboard", "commentattachment", "comment");

    }
}
