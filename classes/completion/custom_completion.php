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
 * Custom completion rules displayed by Moodle core.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\completion;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

/**
 * Provides custom completion functionality.
 */
final class custom_completion extends \core_completion\activity_custom_completion {
    /**
     * Handles get defined custom rules operations for Project Board.
     */
    public static function get_defined_custom_rules(): array {
        return [
            "completioncreated",
            "completiondone",
            "completionproject",
            "completionchecklist",
            "completiongraded",
            "completionminscore",
        ];
    }

    /**
     * Handles get state operations for Project Board.
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $activity = $DB->get_record("projectboard", ["id" => $this->cm->instance], "*", MUST_EXIST);
        $isolated = clone $activity;
        foreach (self::get_defined_custom_rules() as $key) {
            if ($key !== $rule) {
                $isolated->$key = 0;
            }
        }
        $met = \mod_projectboard\completion_service::completed($isolated, $this->cm, $this->userid);
        return $met ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Handles get custom rule descriptions operations for Project Board.
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record("projectboard", ["id" => $this->cm->instance], "*", MUST_EXIST);
        $results = [];
        $keys = [
            "completioncreated" => "completiontasksrule",
            "completiondone" => "completiondonerule",
            "completionproject" => "completionprojectrule",
            "completionchecklist" => "completionchecklistrule",
            "completiongraded" => "completiongraderule",
            "completionminscore" => "completionminscorerule",
        ];
        foreach ($keys as $field => $string) {
            $results[$field] = get_string($string, "projectboard", $activity->$field);
        }
        return $results;
    }

    /**
     * Handles get sort order operations for Project Board.
     */
    public function get_sort_order(): array {
        return array_merge(["completionview", "completionusegrade"], self::get_defined_custom_rules());
    }
}
