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
 * Native Moodle rich-text editor for a task.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\form;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this component.
defined("MOODLE_INTERNAL") || die();

require_once($CFG->libdir . "/formslib.php");

/**
 * Provides task editor form functionality.
 */
final class task_editor_form extends \moodleform {
    /**
     * Handles definition for Project Board.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $params = $this->_customdata;
        $mform->addElement("hidden", "id", (int)$params["cmid"]);
        $mform->setType("id", PARAM_INT);
        $mform->addElement("hidden", "taskid", (int)$params["taskid"]);
        $mform->setType("taskid", PARAM_INT);
        $mform->addElement("hidden", "version", (int)$params["version"]);
        $mform->setType("version", PARAM_INT);
        $mform->addElement("text", "title", get_string("tasktitle", "projectboard"),
            ["size" => 72, "maxlength" => 255]);
        $mform->setType("title", PARAM_TEXT);
        $mform->addRule("title", null, "required", null, "client");
        $mform->addElement("editor", "description_editor", get_string("description", "projectboard"),
            null, ["maxfiles" => 0, "context" => $params["context"], "trusttext" => false]);
        $this->add_action_buttons(true, get_string("save", "projectboard"));
    }
}
