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
 * Persisted task discussion.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides comment service functionality.
 */
final class comment_service {
    /**
     * Handles add operations for Project Board.
     */
    public static function add(\stdClass $activity, \stdClass $cm, array $data): int {
        global $DB, $USER;
        $task = access_manager::require_task((int)($data["taskid"] ?? 0), $activity, $cm, true);
        access_manager::require_action("comment", $cm);
        $format = (int)($data["format"] ?? FORMAT_PLAIN);
        $format = $format === FORMAT_HTML ? FORMAT_HTML : FORMAT_PLAIN;
        $content = trim(clean_param($data["content"] ?? "",
            $format === FORMAT_HTML ? PARAM_CLEANHTML : PARAM_TEXT));
        if (!$content) {
            throw new \invalid_parameter_exception("Empty comment");
        }
        $parentid = (int)($data["parentid"] ?? 0);
        if ($parentid && !$DB->record_exists("projectboard_comment", ["id" => $parentid, "taskid" => $task->id])) {
            throw new \invalid_parameter_exception("Reply does not belong to task");
        }
        $id = $DB->insert_record("projectboard_comment", (object)[
            "taskid" => $task->id, "parentid" => $parentid, "userid" => $USER->id,
            "content" => $content, "format" => $format,
            "timecreated" => time(), "timemodified" => time(),
        ]);
        history_service::record((int)$task->id, "comment_added", null, $id);
        notification_service::task_event($activity, $task, "comment", $USER->id);
        return (int)$id;
    }
}
