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
 * Course activity entry point.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$course = $DB->get_record("course", ["id" => $id], "*", MUST_EXIST);
require_login($course);
$PAGE->set_url(new moodle_url("/mod/projectboard/index.php", ["id" => $id]));
$PAGE->set_title(get_string("modulenameplural", "projectboard"));
$PAGE->set_heading($course->fullname);
$PAGE->set_context(\context_course::instance($id));
$modules = get_all_instances_in_course("projectboard", $course);
$rows = [];
foreach ($modules as $module) {
    $cm = get_coursemodule_from_instance("projectboard", $module->id, $course->id);
    if ($cm && has_capability("mod/projectboard:view", \context_module::instance($cm->id))) {
        $rows[] = [
            "name" => format_string($module->name),
            "url" => (new moodle_url("/mod/projectboard/view.php", ["id" => $cm->id]))->out(false)
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_projectboard/index", [
    "title" => get_string("modulenameplural", "projectboard"), "modules" => $rows
]);
echo $OUTPUT->footer();
