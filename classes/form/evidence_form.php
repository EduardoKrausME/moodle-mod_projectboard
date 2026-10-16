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
 * Evidence upload using Moodle File API.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard\form;

defined("MOODLE_INTERNAL") || die();

require_once($CFG->libdir . "/formslib.php");

final class evidence_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement("hidden", "id", $this->_customdata["cmid"]);
        $mform->setType("id", PARAM_INT);
        $mform->addElement("hidden", "taskid", $this->_customdata["taskid"]);
        $mform->setType("taskid", PARAM_INT);
        $mform->addElement("textarea", "description", get_string("description", "projectboard"),
            ["rows" => 4, "cols" => 65]);
        $mform->setType("description", PARAM_TEXT);
        $options = ["subdirs" => 0, "maxfiles" => 10, "maxbytes" => 0, "accepted_types" => "*"];
        $mform->addElement("filemanager", "files", get_string("addevidence", "projectboard"), null, $options);
        $mform->addRule("files", null, "required", null, "client");
        $this->add_action_buttons(true, get_string("save", "projectboard"));
    }
}
