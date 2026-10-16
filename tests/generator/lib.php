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
 * Moodle test data generator.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

class mod_projectboard_generator extends testing_module_generator {
    public function create_instance($record = null, array $options = null) {
        $record = (object)$record;
        $defaults = [
            "name" => "Project Board",
            "intro" => "",
            "introformat" => FORMAT_HTML,
            "objective" => "",
            "startdate" => 0,
            "enddate" => 0,
            "workmode" => "individual",
            "allowcreate" => 1,
            "allowmove" => 1,
            "allowassign" => 1,
            "maxwip" => 0,
            "requireevidence" => 0,
            "grademode" => "none",
            "grade" => 100,
            "completioncreated" => 0,
            "completiondone" => 0,
            "completionproject" => 0,
            "completionchecklist" => 0,
            "completiongraded" => 0,
            "completionminscore" => 0,
            "showhistory" => 1,
            "notifications" => 0
        ];
        foreach ($defaults as $field => $value) {
            if (!property_exists($record, $field)) {
                $record->$field = $value;
            }
        }
        return parent::create_instance($record, $options);
    }
}
