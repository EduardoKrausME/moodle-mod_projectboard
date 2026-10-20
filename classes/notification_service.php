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
 * Persistent notification delivery and deduplication.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides notification service functionality.
 */
final class notification_service {
    /**
     * Handles send for Project Board.
     */
    public static function send(\stdClass $activity, \stdClass $task, int $userid, string $provider,
            string $periodkey): void {
        global $DB;
        if (!$activity->notifications || !$userid || !in_array($provider,
                ["assignment", "comment", "deadline", "review", "dependency"], true)) {
            return;
        }
        $where = ["taskid" => $task->id, "userid" => $userid,
            "eventname" => $provider, "periodkey" => $periodkey];
        if ($DB->record_exists("projectboard_notice", $where)) {
            return;
        }
        $context = \context_module::instance(get_coursemodule_from_instance("projectboard", $activity->id)->id);
        if (!is_enrolled($context, $userid, "mod/projectboard:view", true)) {
            return;
        }
        $target = \core_user::get_user($userid);
        $sender = \core_user::get_noreply_user();
        $message = new \core\message\message();
        $message->component = "mod_projectboard";
        $message->name = $provider;
        $message->userfrom = $sender;
        $message->userto = $target;
        $message->subject = get_string("tasknotification", "projectboard");
        $message->fullmessage = format_string($task->title, true) . " - " .
            get_string($provider === "deadline" ? "duedate" : "tasknotification", "projectboard");
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = "";
        $message->smallmessage = $message->fullmessage;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url("/mod/projectboard/view.php", [
            "id" => get_coursemodule_from_instance("projectboard", $activity->id)->id, "taskid" => $task->id,
        ]))->out(false);
        $message->contexturlname = $activity->name;
        $transaction = $DB->start_delegated_transaction();
        try {
            $id = $DB->insert_record("projectboard_notice", (object)($where + ["timecreated" => time()]));
        } catch (\dml_write_exception $e) {
            $transaction->allow_commit();
            return;
        }
        $transaction->allow_commit();
        try {
            message_send($message);
        } catch (\Throwable $e) {
            $DB->delete_records("projectboard_notice", ["id" => $id]);
            throw $e;
        }
    }

    /**
     * Handles task event for Project Board.
     */
    public static function task_event(\stdClass $activity, \stdClass $task,
            string $provider, int $exclude = 0): void {
        global $DB;
        if (!$activity->notifications) {
            return;
        }
        $assignees = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
        foreach ($assignees as $userid) {
            if ((int)$userid !== $exclude) {
                self::send($activity, $task, (int)$userid, $provider, $provider . "_" . time());
            }
        }
    }

    /**
     * Handles scheduled deadlines for Project Board.
     */
    public static function scheduled_deadlines(): void {
        global $DB;
        $now = time();
        $tasks = $DB->get_records_sql(
            "SELECT t.*, b.projectboardid FROM {projectboard_task} t
               JOIN {projectboard_board} b ON b.id = t.boardid
               JOIN {projectboard_column} c ON c.id = t.columnid
              WHERE t.archived = 0 AND t.duedate > 0 AND c.isdone = 0
                AND t.duedate <= :threshold", ["threshold" => $now + 86400]);
        foreach ($tasks as $task) {
            $activity = $DB->get_record("projectboard", ["id" => $task->projectboardid]);
            if (!$activity || !$activity->notifications) {
                continue;
            }
            $key = $task->duedate < $now ? "overdue_" . $task->duedate : "due_" . $task->duedate;
            $assignees = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
            foreach ($assignees as $userid) {
                self::send($activity, $task, (int)$userid, "deadline", $key);
            }
        }
    }
}
