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
 * Privacy API integration for projects and user evidence.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Provides provider functionality.
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        core_userlist_provider {
    /** @var array<string, string> User reference fields by storage table. */
    private const TABLES = [
        "projectboard_task" => "createdby",
        "projectboard_comment" => "userid",
        "projectboard_evidence" => "userid",
        "projectboard_assignee" => "userid",
        "projectboard_check" => "userid",
        "projectboard_history" => "userid",
        "projectboard_notice" => "userid",
        "projectboard_grade" => "userid",
        "projectboard_board" => "owneruserid",
        "projectboard_score" => "userid",
    ];

    /**
     * Handles get metadata for Project Board.
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $table => $field) {
            $fields = [$field => "privacy:metadata:" . $table];
            if (in_array($table, ["projectboard_grade", "projectboard_score"], true)) {
                $fields["gradedby"] = "privacy:metadata:" . $table;
            }
            foreach (["content", "description", "feedback", "oldvalue", "newvalue", "title", "name",
                "priority", "tags", "grade"] as $candidate) {
                if (in_array($candidate, explode(",", [
                    "projectboard_comment" => "content",
                    "projectboard_evidence" => "description",
                    "projectboard_grade" => "feedback",
                    "projectboard_history" => "oldvalue,newvalue",
                    "projectboard_task" => "title,description,priority,tags",
                    "projectboard_board" => "name",
                ][$table] ?? ""), true)) {
                    $fields[$candidate] = "privacy:metadata:" . $table;
                }
            }
            $collection->add_database_table($table, $fields, "privacy:metadata:" . $table);
        }
        $collection->add_subsystem_link("core_files", [], "privacy:metadata:projectboard_files");
        $collection->add_subsystem_link("core_message", [], "privacy:metadata:projectboard_notice");
        return $collection;
    }

    /**
     * Handles activity for Project Board.
     */
    private static function activity(context $context): ?stdClass {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return null;
        }
        $cm = get_coursemodule_from_id("projectboard", $context->instanceid);
        return $cm ? $DB->get_record("projectboard", ["id" => $cm->instance]) ?: null : null;
    }

    /**
     * Handles users for Project Board.
     */
    private static function users(stdClass $activity): array {
        global $DB;
        $sql = "SELECT t.id FROM {projectboard_task} t
                  JOIN {projectboard_board} b ON b.id = t.boardid
                 WHERE b.projectboardid = ?";
        $ids = $DB->get_fieldset_sql($sql, [$activity->id]);
        $users = [];
        foreach ($DB->get_records("projectboard_board", ["projectboardid" => $activity->id]) as $board) {
            if ($board->owneruserid > 0) {
                $users[$board->owneruserid] = (int)$board->owneruserid;
            }
        }
        foreach ($DB->get_records("projectboard_grade", ["projectboardid" => $activity->id]) as $grade) {
            $users[$grade->userid] = (int)$grade->userid;
            if ($grade->gradedby) {
                $users[$grade->gradedby] = (int)$grade->gradedby;
            }
        }
        $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?", [$activity->id]);
        foreach ($criteria as $criterionid) {
            foreach ($DB->get_records("projectboard_score", ["criterionid" => $criterionid]) as $score) {
                if ($score->userid > 0) {
                    $users[$score->userid] = (int)$score->userid;
                }
                if ($score->gradedby > 0) {
                    $users[$score->gradedby] = (int)$score->gradedby;
                }
            }
        }
        foreach ($ids as $taskid) {
            $task = $DB->get_record("projectboard_task", ["id" => $taskid]);
            if ($task->createdby) {
                $users[$task->createdby] = (int)$task->createdby;
            }
            foreach (["projectboard_comment", "projectboard_evidence", "projectboard_assignee",
                "projectboard_check", "projectboard_history", "projectboard_notice"] as $table) {
                foreach ($DB->get_records($table, ["taskid" => $taskid]) as $row) {
                    if ($row->userid) {
                        $users[$row->userid] = (int)$row->userid;
                    }
                }
            }
        }
        return array_values($users);
    }

    /**
     * Handles get contexts for userid for Project Board.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contexts = new contextlist();
        // Identify activity contexts from the records that actually refer to this user.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modulename
                   AND (
                       EXISTS (
                           SELECT 1 FROM {projectboard_board} b
                            WHERE b.projectboardid = cm.instance
                              AND b.owneruserid = :boardowner
                       )
                       OR EXISTS (
                           SELECT 1 FROM {projectboard_grade} g
                            WHERE g.projectboardid = cm.instance
                              AND (g.userid = :gradeowner OR g.gradedby = :gradegrader)
                       )
                       OR EXISTS (
                           SELECT 1 FROM {projectboard_criterion} c
                           JOIN {projectboard_score} s ON s.criterionid = c.id
                            WHERE c.projectboardid = cm.instance
                              AND (s.userid = :scoreowner OR s.gradedby = :scoregrader)
                       )
                       OR EXISTS (
                           SELECT 1 FROM {projectboard_board} b
                           JOIN {projectboard_task} t ON t.boardid = b.id
                            WHERE b.projectboardid = cm.instance
                              AND (
                                  t.createdby = :taskcreator
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_comment} pc
                                       WHERE pc.taskid = t.id AND pc.userid = :commentuser
                                  )
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_evidence} pe
                                       WHERE pe.taskid = t.id AND pe.userid = :evidenceuser
                                  )
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_assignee} pa
                                       WHERE pa.taskid = t.id AND pa.userid = :assigneeuser
                                  )
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_check} ck
                                       WHERE ck.taskid = t.id AND ck.userid = :checkuser
                                  )
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_history} ph
                                       WHERE ph.taskid = t.id AND ph.userid = :historyuser
                                  )
                                  OR EXISTS (
                                      SELECT 1 FROM {projectboard_notice} pn
                                       WHERE pn.taskid = t.id AND pn.userid = :noticeuser
                                  )
                              )
                       )
                   )";
        $params = [
            "contextlevel" => CONTEXT_MODULE,
            "modulename" => "projectboard",
            "boardowner" => $userid,
            "gradeowner" => $userid,
            "gradegrader" => $userid,
            "scoreowner" => $userid,
            "scoregrader" => $userid,
            "taskcreator" => $userid,
            "commentuser" => $userid,
            "evidenceuser" => $userid,
            "assigneeuser" => $userid,
            "checkuser" => $userid,
            "historyuser" => $userid,
            "noticeuser" => $userid,
        ];
        $contexts->add_from_sql($sql, $params);
        return $contexts;
    }

    /**
     * Handles get users in context for Project Board.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $activity = self::activity($userlist->get_context());
        if ($activity) {
            $userlist->add_users(self::users($activity));
        }
    }

    /**
     * Handles export user data for Project Board.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $activity = self::activity($context);
            if (!$activity) {
                continue;
            }
            $data = (object)["tasks" => [], "comments" => [], "evidence" => [],
                "grades" => [], "assignments" => [], "checklists" => [], "history" => [],
                "notices" => [], "criteria" => [], "ownedboards" => [],
                "gradesgiven" => [], "criteriagiven" => []];
            $boards = $DB->get_records("projectboard_board", ["projectboardid" => $activity->id]);
            foreach ($boards as $board) {
                if ((int)$board->owneruserid === $userid) {
                    $data->ownedboards[] = $board;
                }
                foreach ($DB->get_records("projectboard_task", ["boardid" => $board->id]) as $task) {
                    if ($task->createdby == $userid) {
                        $data->tasks[] = $task;
                    }
                    foreach ([
                        "projectboard_assignee" => "assignments",
                        "projectboard_check" => "checklists",
                        "projectboard_history" => "history",
                        "projectboard_notice" => "notices",
                    ] as $table => $collection) {
                        foreach ($DB->get_records($table, ["taskid" => $task->id, "userid" => $userid]) as $entry) {
                            $data->$collection[] = $entry;
                        }
                    }
                    foreach ($DB->get_records("projectboard_comment", ["taskid" => $task->id, "userid" => $userid]) as $comment) {
                        $data->comments[] = $comment;
                    }
                    foreach ($DB->get_records("projectboard_evidence", ["taskid" => $task->id, "userid" => $userid]) as $evidence) {
                        $data->evidence[] = $evidence;
                    }
                }
            }
            $data->grades = array_values($DB->get_records("projectboard_grade",
                ["projectboardid" => $activity->id, "userid" => $userid]));
            $data->gradesgiven = array_values($DB->get_records("projectboard_grade",
                ["projectboardid" => $activity->id, "gradedby" => $userid]));
            $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?",
                [$activity->id]);
            foreach ($criteria as $criterionid) {
                foreach ($DB->get_records("projectboard_score", [
                    "criterionid" => $criterionid, "userid" => $userid,
                ]) as $score) {
                    $data->criteria[] = $score;
                }
                foreach ($DB->get_records("projectboard_score", [
                    "criterionid" => $criterionid, "gradedby" => $userid,
                ]) as $score) {
                    $data->criteriagiven[] = $score;
                }
            }
            $writer = writer::with_context($context);
            $writer->export_data([get_string("pluginname", "projectboard")], $data);
            foreach ($data->evidence as $evidence) {
                $writer->export_area_files([get_string("evidence", "projectboard"), (string)$evidence->id],
                    "mod_projectboard", "evidence", $evidence->id);
            }
            foreach ($data->comments as $comment) {
                $writer->export_area_files([get_string("comment", "projectboard"), (string)$comment->id],
                    "mod_projectboard", "commentattachment", $comment->id);
            }
        }
    }

    /**
     * Handles delete data for all users in context for Project Board.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        $activity = self::activity($context);
        if ($activity) {
            foreach (self::users($activity) as $userid) {
                self::erase($activity, $context, (int)$userid);
            }
        }
    }

    /**
     * Handles delete data for user for Project Board.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            $activity = self::activity($context);
            if ($activity) {
                self::erase($activity, $context, (int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Handles delete data for users for Project Board.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $activity = self::activity($userlist->get_context());
        if ($activity) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase($activity, $userlist->get_context(), (int)$userid);
            }
        }
    }

    /**
     * Handles erase for Project Board.
     */
    private static function erase(stdClass $activity, context $context, int $userid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        foreach ($DB->get_records("projectboard_board", ["projectboardid" => $activity->id]) as $board) {
            foreach ($DB->get_records("projectboard_task", ["boardid" => $board->id]) as $task) {
                foreach (["projectboard_evidence" => "evidence",
                    "projectboard_comment" => "commentattachment"] as $table => $area) {
                    foreach ($DB->get_records($table, ["taskid" => $task->id, "userid" => $userid]) as $row) {
                        get_file_storage()->delete_area_files($context->id, "mod_projectboard", $area, $row->id);
                        if ($table === "projectboard_comment") {
                            $DB->set_field("projectboard_comment", "parentid", 0, ["parentid" => $row->id]);
                        }
                        $DB->delete_records($table, ["id" => $row->id]);
                    }
                }
                foreach (["projectboard_assignee", "projectboard_notice"] as $table) {
                    $DB->delete_records($table, ["taskid" => $task->id, "userid" => $userid]);
                }
                foreach (["projectboard_history", "projectboard_check"] as $table) {
                    $DB->set_field($table, "userid", 0, ["taskid" => $task->id, "userid" => $userid]);
                }
                if ($task->createdby == $userid) {
                    $DB->set_field("projectboard_task", "createdby", 0, ["id" => $task->id]);
                }
            }
            if ($board->owneruserid == $userid) {
                // Preserve collaborative project records while irreversibly severing the account reference.
                // Negative synthetic ownership identifiers avoid collisions with the unique board index.
                $DB->set_field("projectboard_board", "owneruserid", -1 * (int)$board->id,
                    ["id" => $board->id]);
                $DB->set_field("projectboard_board", "name", get_string("board", "projectboard"),
                    ["id" => $board->id]);
            }
        }
        $DB->delete_records("projectboard_grade", ["projectboardid" => $activity->id, "userid" => $userid]);
        $DB->set_field_select("projectboard_grade", "gradedby", 0,
            "projectboardid = ? AND gradedby = ?", [$activity->id, $userid]);
        $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?", [$activity->id]);
        foreach ($criteria as $criterionid) {
            $DB->delete_records("projectboard_score", ["criterionid" => $criterionid, "userid" => $userid]);
            $DB->set_field("projectboard_score", "gradedby", 0,
                ["criterionid" => $criterionid, "gradedby" => $userid]);
        }
        $transaction->allow_commit();
    }
}
