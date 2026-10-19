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
 * Course activity configuration form.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this entry point.
defined("MOODLE_INTERNAL") || die();

require_once($CFG->dirroot . "/course/moodleform_mod.php");

/**
 * Provides mod projectboard mod form functionality.
 */
class mod_projectboard_mod_form extends moodleform_mod {
    /**
     * Definition for the Project Board activity.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement("header", "general", get_string("general", "form"));
        $mform->addElement("text", "name", get_string("projectboardname", "projectboard"), ["size" => 64]);
        $mform->setType("name", PARAM_TEXT);
        $mform->addRule("name", null, "required", null, "client");
        $this->standard_intro_elements();
        $mform->addElement("textarea", "objective", get_string("objective", "projectboard"),
            ["rows" => 4, "cols" => 70]);
        $mform->setType("objective", PARAM_TEXT);
        $mform->addElement("date_time_selector", "startdate", get_string("startdate", "projectboard"),
            ["optional" => true]);
        $mform->addElement("date_time_selector", "enddate", get_string("enddate", "projectboard"),
            ["optional" => true]);
        $mform->addElement("select", "workmode", get_string("workmode", "projectboard"), [
            "individual" => get_string("individual", "projectboard"),
            "groups" => get_string("groups", "projectboard"),
            "collective" => get_string("collective", "projectboard"),
        ]);
        $mform->addElement("advcheckbox", "allowcreate", get_string("allowcreate", "projectboard"));
        $mform->addElement("advcheckbox", "allowmove", get_string("allowmove", "projectboard"));
        $mform->addElement("advcheckbox", "allowassign", get_string("allowassign", "projectboard"));
        $mform->setDefault("allowcreate", 1);
        $mform->setDefault("allowmove", 1);
        $mform->addElement("text", "maxwip", get_string("maxwip", "projectboard"), ["size" => 8]);
        $mform->setType("maxwip", PARAM_INT);
        $mform->setDefault("maxwip", 0);
        $mform->addElement("advcheckbox", "requireevidence", get_string("requireevidence", "projectboard"));
        $mform->addElement("advcheckbox", "showhistory", get_string("showhistory", "projectboard"));
        $mform->setDefault("showhistory", 1);
        $mform->addElement("advcheckbox", "notifications", get_string("notifications", "projectboard"));
        $mform->setDefault("notifications", 1);
        $mform->addElement("header", "gradingsection", get_string("grademode", "projectboard"));
        $mform->addElement("select", "grademode", get_string("grademode", "projectboard"), [
            "none" => get_string("none", "projectboard"),
            "project" => get_string("project", "projectboard"),
            "individual" => get_string("individualgrade", "projectboard"),
            "criteria" => get_string("criteria", "projectboard"),
        ]);
        $mform->addElement("text", "grade", get_string("grade", "projectboard"), ["size" => 8]);
        $mform->setType("grade", PARAM_FLOAT);
        $mform->setDefault("grade", 100);
        $mform->hideIf("grade", "grademode", "eq", "none");
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add completion rules for the Project Board activity.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        foreach (["completioncreated", "completiondone"] as $field) {
            $mform->addElement("text", $field, get_string($field, "projectboard"), ["size" => 8]);
            $mform->setType($field, PARAM_INT);
            $mform->setDefault($field, 0);
        }
        foreach (["completionproject", "completionchecklist", "completiongraded"] as $field) {
            $mform->addElement("advcheckbox", $field, get_string($field, "projectboard"));
        }
        $mform->addElement("text", "completionminscore", get_string("completionminscore", "projectboard"),
            ["size" => 8]);
        $mform->setType("completionminscore", PARAM_FLOAT);
        $mform->setDefault("completionminscore", 0);
        return ["completioncreated", "completiondone", "completionproject",
            "completionchecklist", "completiongraded", "completionminscore"];
    }

    /**
     * Completion rule enabled for the Project Board activity.
     */
    public function completion_rule_enabled($data) {
        return !empty($data["completioncreated"]) || !empty($data["completiondone"]) ||
            !empty($data["completionproject"]) || !empty($data["completionchecklist"]) ||
            !empty($data["completiongraded"]) || !empty($data["completionminscore"]);
    }

    /**
     * Validation for the Project Board activity.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data["startdate"]) && !empty($data["enddate"]) &&
                $data["startdate"] > $data["enddate"]) {
            $errors["enddate"] = get_string("dateinvalid", "projectboard");
        }
        foreach (["maxwip", "completioncreated", "completiondone"] as $field) {
            if (isset($data[$field]) && (int)$data[$field] < 0) {
                $errors[$field] = get_string("invaliddate", "projectboard");
            }
        }
        if (isset($data["grade"]) && (float)$data["grade"] <= 0 &&
                ($data["grademode"] ?? "none") !== "none") {
            $errors["grade"] = get_string("errorinvalid", "projectboard");
        }
        return $errors;
    }
}
