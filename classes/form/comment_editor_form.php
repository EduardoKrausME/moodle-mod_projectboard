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
 * Native Moodle comment editor with file attachments.
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
 * Provides comment editor form functionality.
 */
final class comment_editor_form extends \moodleform {
    /**
     * Handles definition operations for Project Board.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;
        foreach (["id" => "cmid", "taskid" => "taskid", "parentid" => "parentid"] as $field => $param) {
            $mform->addElement("hidden", $field, (int)$data[$param]);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addElement("editor", "content_editor", get_string("comment", "projectboard"),
            null, ["maxfiles" => 0, "context" => $data["context"]]);
        $mform->addElement("filemanager", "files", get_string("addevidence", "projectboard"),
            null, ["subdirs" => 0, "maxfiles" => 5, "maxbytes" => 0, "accepted_types" => "*"]);
        $this->add_action_buttons(true, get_string("addcomment", "projectboard"));
    }
}
