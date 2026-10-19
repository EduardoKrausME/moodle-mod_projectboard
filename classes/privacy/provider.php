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
 * Privacy API integration for projects and user evidence.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\privacy;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides provider functionality.
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
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
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        foreach (self::TABLES as $table => $field) {
            $fields = [$field => "privacy:metadata:" . $table];
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
        $collection->add_subsystem_link("core_files", [], "privacy:metadata:projectboard_evidence");
        $collection->add_subsystem_link("core_message", [], "privacy:metadata:projectboard_notice");
        return $collection;
    }

    /**
     * Handles activity for Project Board.
     */
    private static function activity(\context $context): ?\stdClass {
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
    private static function users(\stdClass $activity): array {
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
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;
        $contexts = new \core_privacy\local\request\contextlist();
        $sql = "SELECT ctx.id FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE ctx.contextlevel = :level AND m.name = :name";
        foreach ($DB->get_records_sql($sql, ["level" => CONTEXT_MODULE, "name" => "projectboard"]) as $row) {
            $activity = self::activity(\context::instance_by_id($row->id));
            if ($activity && in_array($userid, self::users($activity), true)) {
                $contexts->add_context($row->id);
            }
        }
        return $contexts;
    }

    /**
     * Handles get users in context for Project Board.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        $activity = self::activity($userlist->get_context());
        if ($activity) {
            $userlist->add_users(self::users($activity));
        }
    }

    /**
     * Handles export user data for Project Board.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $activity = self::activity($context);
            if (!$activity) {
                continue;
            }
            $data = (object)["tasks" => [], "comments" => [], "evidence" => [],
                "grades" => [], "assignments" => [], "checklists" => [], "history" => [],
                "notices" => [], "criteria" => [], "ownedboards" => []];
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
            $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?",
                [$activity->id]);
            foreach ($criteria as $criterionid) {
                foreach ($DB->get_records("projectboard_score", [
                    "criterionid" => $criterionid, "userid" => $userid,
                ]) as $score) {
                    $data->criteria[] = $score;
                }
            }
            $writer = \core_privacy\local\request\writer::with_context($context);
            $writer->export_data([get_string("pluginname", "projectboard")], $data);
            foreach ($data->evidence as $evidence) {
                $writer->export_area_files([get_string("evidence", "projectboard")],
                    "mod_projectboard", "evidence", $evidence->id);
            }
        }
    }

    /**
     * Handles delete data for all users in context for Project Board.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
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
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
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
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
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
    private static function erase(\stdClass $activity, \context $context, int $userid): void {
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
        $DB->execute("UPDATE {projectboard_grade} SET gradedby = 0 WHERE projectboardid = ? AND gradedby = ?",
            [$activity->id, $userid]);
        $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?", [$activity->id]);
        foreach ($criteria as $criterionid) {
            $DB->delete_records("projectboard_score", ["criterionid" => $criterionid, "userid" => $userid]);
            $DB->set_field("projectboard_score", "gradedby", 0,
                ["criterionid" => $criterionid, "gradedby" => $userid]);
        }
        $transaction->allow_commit();
    }
}
