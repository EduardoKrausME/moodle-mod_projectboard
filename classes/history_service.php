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
 * Immutable functional audit trail.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides history service functionality.
 */
final class history_service {
    /**
     * Handles record for Project Board.
     */
    public static function record(int $taskid, string $action, $old = null, $new = null): void {
        global $DB, $USER;
        $DB->insert_record("projectboard_history", (object)[
            "taskid" => $taskid,
            "userid" => (int)$USER->id,
            "action" => clean_param($action, PARAM_ALPHANUMEXT),
            "oldvalue" => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            "newvalue" => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            "timecreated" => time(),
        ]);
    }

    /**
     * Handles entries for Project Board.
     */
    public static function entries(int $taskid, int $limit = 100): array {
        global $DB;
        return array_values($DB->get_records("projectboard_history", ["taskid" => $taskid],
            "timecreated DESC, id DESC", "*", 0, $limit));
    }
}
