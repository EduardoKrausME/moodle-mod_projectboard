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
 * Mustache view-model construction for project boards.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\presentation;

defined("MOODLE_INTERNAL") || die();

final class board_data {
    public static function page(\stdClass $activity, \stdClass $cm, ?\stdClass $board, array $boards): array {
        global $USER;
        $context = \context_module::instance($cm->id);
        $options = [];
        foreach ($boards as $candidate) {
            $options[] = [
                "id" => (int)$candidate->id, "name" => $candidate->name,
                "selected" => $board && $candidate->id == $board->id
            ];
        }
        $canmanage = \mod_projectboard\access_manager::can_manage($cm);
        $canadd = has_capability("mod/projectboard:addtask", $context) && ($activity->allowcreate || $canmanage);
        $canmove = has_capability("mod/projectboard:movetask", $context) && ($activity->allowmove || $canmanage);
        return [
            "cmid" => (int)$cm->id,
            "title" => format_string($activity->name),
            "objective" => format_text($activity->objective, FORMAT_PLAIN, ["context" => $context]),
            "boards" => $options,
            "hasboards" => !empty($options),
            "selectedboardid" => $board ? (int)$board->id : 0,
            "board" => $board ? self::board($activity, $cm, $board) : null,
            "canadd" => $canadd,
            "canmove" => $canmove,
            "canmanagecolumns" => has_capability("mod/projectboard:managecolumns", $context),
            "canreports" => has_capability("mod/projectboard:viewreports", $context),
            "cangrade" => has_capability("mod/projectboard:grade", $context) && $activity->grademode !== "none",
            "reporturl" => (new \moodle_url("/mod/projectboard/report.php", ["id" => $cm->id]))->out(false),
            "columnoptions" => array_map(static function($col) {
                return ["id" => $col->id, "name" => $col->name];
            }, \mod_projectboard\board_service::columns($activity)),
            "strings" => self::strings()
        ];
    }

    public static function board(\stdClass $activity, \stdClass $cm, \stdClass $board): array {
        global $DB;
        $context = \context_module::instance($cm->id);
        $manage = \mod_projectboard\access_manager::can_manage($cm);
        $canmove = has_capability("mod/projectboard:movetask", $context) && ($activity->allowmove || $manage);
        $canadd = has_capability("mod/projectboard:addtask", $context) && ($activity->allowcreate || $manage);
        $columns = [];
        $all = \mod_projectboard\board_service::columns($activity);
        $tasks = $DB->get_records("projectboard_task",
            ["boardid" => $board->id, "archived" => 0], "sortorder ASC, id ASC");
        foreach ($all as $column) {
            $cards = [];
            foreach ($tasks as $task) {
                if ((int)$task->columnid !== (int)$column->id) {
                    continue;
                }
                $check = \mod_projectboard\checklist_service::progress((int)$task->id);
                $count = $DB->count_records("projectboard_comment", ["taskid" => $task->id]);
                $users = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
                $assignees = [];
                foreach ($users as $userid) {
                    $person = $DB->get_record("user", ["id" => $userid], "id, firstname, lastname");
                    if ($person) {
                        $assignees[] = ["name" => fullname($person), "id" => (int)$person->id];
                    }
                }
                $blocked = \mod_projectboard\dependency_service::blockers((int)$task->id);
                $cards[] = [
                    "id" => (int)$task->id, "title" => $task->title,
                    "priority" => $task->priority,
                    "prioritylabel" => get_string($task->priority, "projectboard"),
                    "tags" => $task->tags,
                    "duedate" => $task->duedate ? userdate($task->duedate, get_string("strftimedatetime", "langconfig")) : "",
                    "overdue" => $task->duedate && $task->duedate < time() && !$column->isdone,
                    "isblocked" => !empty($blocked),
                    "comments" => (int)$count,
                    "checkdone" => $check["done"], "checktotal" => $check["total"],
                    "checkpercent" => $check["percent"], "hascheck" => $check["total"] > 0,
                    "assignees" => $assignees, "hasassignees" => !empty($assignees),
                    "version" => (int)$task->version,
                    "canedit" => \mod_projectboard\access_manager::may_edit($task, $cm),
                    "canmove" => $canmove,
                    "positions" => array_map(static function($other) use ($column) {
                        return ["id" => (int)$other->id, "name" => $other->name,
                            "selected" => $other->id == $column->id];
                    }, $all)
                ];
            }
            $transitions = [];
            foreach ($all as $destination) {
                if ($destination->id === $column->id) {
                    continue;
                }
                $rule = $DB->get_record("projectboard_transition", [
                    "fromcolumnid" => $column->id, "tocolumnid" => $destination->id
                ]);
                $transitions[] = [
                    "fromid" => (int)$column->id, "toid" => (int)$destination->id,
                    "name" => $destination->name, "allowed" => !$rule || $rule->allowed,
                    "blocked" => $rule && !$rule->allowed
                ];
            }
            $columns[] = [
                "id" => (int)$column->id, "name" => $column->name,
                "colour" => $column->colour, "wiplimit" => (int)$column->wiplimit,
                "transitions" => $transitions,
                "count" => count($cards), "cards" => $cards,
                "hascards" => !empty($cards), "canadd" => $canadd,
                "canmanage" => has_capability("mod/projectboard:managecolumns", $context)
            ];
        }
        return [
            "boardid" => (int)$board->id,
            "name" => $board->name,
            "columns" => $columns, "hascolumns" => !empty($columns),
            "canadd" => $canadd, "canmove" => $canmove,
            "canmanagecolumns" => has_capability("mod/projectboard:managecolumns", $context),
            "strings" => self::strings()
        ];
    }

    public static function task(\stdClass $activity, \stdClass $cm, \stdClass $task): array {
        global $DB;
        $context = \context_module::instance($cm->id);
        $canedit = \mod_projectboard\access_manager::may_edit($task, $cm);
        $cancomment = has_capability("mod/projectboard:comment", $context);
        $canreview = has_capability("mod/projectboard:review", $context);
        $canfile = has_capability("mod/projectboard:addevidence", $context);
        $check = \mod_projectboard\checklist_service::progress((int)$task->id);
        $items = [];
        foreach ($check["items"] as $item) {
            $items[] = [
                "id" => (int)$item->id, "title" => $item->title,
                "done" => (bool)$item->done, "taskid" => (int)$task->id,
                "canedit" => $canedit
            ];
        }
        $comments = [];
        $entries = $DB->get_records("projectboard_comment", ["taskid" => $task->id],
            "timecreated ASC, id ASC");
        foreach ($entries as $entry) {
            $author = $DB->get_record("user", ["id" => $entry->userid], "id, firstname, lastname");
            $comments[] = [
                "id" => (int)$entry->id, "parentid" => (int)$entry->parentid,
                "author" => $author ? fullname($author) : "",
                "content" => format_text($entry->content, $entry->format, ["context" => $context]),
                "when" => userdate($entry->timecreated)
            ];
        }
        $evidences = [];
        $rows = $DB->get_records("projectboard_evidence", ["taskid" => $task->id],
            "timecreated DESC");
        foreach ($rows as $evidence) {
            $files = get_file_storage()->get_area_files($context->id,
                "mod_projectboard", "evidence", $evidence->id, "id", false);
            foreach ($files as $file) {
                $url = \moodle_url::make_pluginfile_url($context->id, "mod_projectboard",
                    "evidence", $evidence->id, $file->get_filepath(), $file->get_filename());
                $evidences[] = [
                    "id" => (int)$evidence->id, "description" => $evidence->description,
                    "filename" => $file->get_filename(), "url" => $url->out(false)
                ];
            }
        }
        $users = $DB->get_fieldset_select("projectboard_assignee", "userid", "taskid = ?", [$task->id]);
        $dependencies = [];
        $blocked = \mod_projectboard\dependency_service::blockers((int)$task->id);
        foreach ($DB->get_records("projectboard_dep", ["taskid" => $task->id]) as $dependency) {
            $dependson = $DB->get_record("projectboard_task", ["id" => $dependency->dependsid], "id, title");
            if ($dependson) {
                $dependencies[] = ["id" => (int)$dependson->id, "title" => $dependson->title,
                    "taskid" => (int)$task->id, "canedit" => $canedit];
            }
        }
        $history = [];
        if ($activity->showhistory || \mod_projectboard\access_manager::can_manage($cm)) {
            foreach (\mod_projectboard\history_service::entries((int)$task->id) as $entry) {
                $history[] = [
                    "action" => $entry->action, "userid" => (int)$entry->userid,
                    "when" => userdate($entry->timecreated),
                    "oldvalue" => $entry->oldvalue, "newvalue" => $entry->newvalue
                ];
            }
        }
        $columns = [];
        foreach (\mod_projectboard\board_service::columns($activity) as $column) {
            $columns[] = ["id" => (int)$column->id, "name" => $column->name,
                "selected" => $column->id == $task->columnid];
        }
        return [
            "id" => (int)$task->id, "title" => $task->title,
            "description" => $task->description,
            "priority" => $task->priority, "tags" => $task->tags,
            "startdate" => $task->startdate ? userdate($task->startdate, "%Y-%m-%dT%H:%M") : "",
            "duedate" => $task->duedate ? userdate($task->duedate, "%Y-%m-%dT%H:%M") : "",
            "estimate" => (int)$task->estimate, "version" => (int)$task->version,
            "boardid" => (int)$task->boardid,
            "canedit" => $canedit, "cancomment" => $cancomment, "canreview" => $canreview,
            "canfile" => $canfile, "canmove" => has_capability("mod/projectboard:movetask", $context),
            "canassign" => has_capability("mod/projectboard:assign", $context) &&
                ($activity->allowassign || \mod_projectboard\access_manager::can_manage($cm)),
            "canadd" => has_capability("mod/projectboard:addtask", $context),
            "columns" => $columns, "checklist" => $items, "checkpercent" => $check["percent"],
            "comments" => $comments, "evidences" => $evidences,
            "dependencies" => $dependencies, "blocked" => !empty($blocked),
            "assigneeids" => implode(",", $users),
            "evidenceurl" => (new \moodle_url("/mod/projectboard/evidence.php", [
                "id" => $cm->id, "taskid" => $task->id
            ]))->out(false),
            "strings" => self::strings(),
            "history" => $history,
            "showhistory" => !empty($history)
        ];
    }

    private static function strings(): array {
        $keys = ["board", "column", "addtask", "tasktitle", "description", "save", "cancel",
            "priority", "normal", "low", "high", "urgent", "duedate", "start", "estimate",
            "tags", "assignees", "archive", "duplicate", "taskedit", "move", "comment",
            "addcomment", "addevidence", "evidence", "taskchecklist", "addcheck",
            "adddependency", "subtask", "history", "managecolumns", "columnname",
            "columncolour", "wiplimit", "addcolumn", "archivecolumn", "moveup", "movedown",
            "blocked", "late", "completed", "report", "filter", "search", "status",
            "editcolumn", "transition",
            "grade", "feedback", "managegrade", "sendreview", "approve", "reopen",
            "returnreview", "reviewcomment", "unassigned", "noevidence", "selectboard", "actions"];
        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = get_string($key, "projectboard");
        }
        return $strings;
    }
}
