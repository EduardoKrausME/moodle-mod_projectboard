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
 * Required Moodle activity callbacks.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guards this entry point.
defined("MOODLE_INTERNAL") || die();

/**
 * Projectboard supports for the Project Board activity.
 */
function projectboard_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Projectboard add instance for the Project Board activity.
 */
function projectboard_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $id = $DB->insert_record("projectboard", $data);
    \mod_projectboard\board_service::default_columns((int)$id);
    $data->id = $id;
    \mod_projectboard\grading_service::update_item($data);
    return $id;
}

/**
 * Projectboard update instance for the Project Board activity.
 */
function projectboard_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record("projectboard", $data);
    \mod_projectboard\grading_service::sync($data);
    return true;
}

/**
 * Projectboard delete instance for the Project Board activity.
 */
function projectboard_delete_instance($id) {
    global $DB;
    if (!$activity = $DB->get_record("projectboard", ["id" => $id])) {
        return false;
    }
    $transaction = $DB->start_delegated_transaction();
    $boards = $DB->get_fieldset_select("projectboard_board", "id", "projectboardid = ?", [$id]);
    foreach ($boards as $boardid) {
        $tasks = $DB->get_fieldset_select("projectboard_task", "id", "boardid = ?", [$boardid]);
        foreach ($tasks as $taskid) {
            $evidences = $DB->get_fieldset_select("projectboard_evidence", "id", "taskid = ?", [$taskid]);
            $cm = get_coursemodule_from_instance("projectboard", $id, $activity->course);
            if ($cm) {
                $context = \context_module::instance($cm->id);
                foreach ($evidences as $evidenceid) {
                    get_file_storage()->delete_area_files($context->id, "mod_projectboard", "evidence", $evidenceid);
                }
            }
            foreach (["projectboard_assignee", "projectboard_check", "projectboard_comment",
                "projectboard_evidence", "projectboard_history", "projectboard_notice",
                "projectboard_dep"] as $table) {
                $DB->delete_records($table, ["taskid" => $taskid]);
            }
            $DB->delete_records("projectboard_dep", ["dependsid" => $taskid]);
        }
        $DB->delete_records("projectboard_task", ["boardid" => $boardid]);
    }
    $DB->delete_records("projectboard_board", ["projectboardid" => $id]);
    $columns = $DB->get_fieldset_select("projectboard_column", "id", "projectboardid = ?", [$id]);
    foreach ($columns as $columnid) {
        $DB->delete_records("projectboard_transition", ["fromcolumnid" => $columnid]);
        $DB->delete_records("projectboard_transition", ["tocolumnid" => $columnid]);
    }
    $DB->delete_records("projectboard_column", ["projectboardid" => $id]);
    $DB->delete_records("projectboard_grade", ["projectboardid" => $id]);
    $criteria = $DB->get_fieldset_select("projectboard_criterion", "id", "projectboardid = ?", [$id]);
    foreach ($criteria as $criterionid) {
        $DB->delete_records("projectboard_score", ["criterionid" => $criterionid]);
    }
    $DB->delete_records("projectboard_criterion", ["projectboardid" => $id]);
    $DB->delete_records("projectboard", ["id" => $id]);
    $transaction->allow_commit();
    $activity->grademode = "none";
    \mod_projectboard\grading_service::update_item($activity);
    return true;
}

/**
 * Projectboard grade item update for the Project Board activity.
 */
function projectboard_grade_item_update($activity, $grades = null) {
    \mod_projectboard\grading_service::update_item($activity, $grades);
}

/**
 * Projectboard update grades for the Project Board activity.
 */
function projectboard_update_grades($activity, $userid = 0, $nullifnone = true) {
    \mod_projectboard\grading_service::sync($activity, (int)$userid);
}

/**
 * Projectboard get completion state for the Project Board activity.
 */
function projectboard_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    $activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
    $custom = \mod_projectboard\completion_service::completed($activity, $cm, (int)$userid);
    $hascustom = (bool)($activity->completioncreated || $activity->completiondone ||
        $activity->completionproject || $activity->completionchecklist ||
        $activity->completiongraded || $activity->completionminscore);
    return $hascustom ? $custom : $type;
}

/**
 * Projectboard get coursemodule info for the Project Board activity.
 */
function projectboard_get_coursemodule_info($cm) {
    global $DB;
    $activity = $DB->get_record("projectboard", ["id" => $cm->instance], "id, name, intro, introformat");
    if (!$activity) {
        return false;
    }
    $info = new \cached_cm_info();
    $info->name = $activity->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro("projectboard", $activity, $cm->id, false);
    }
    if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $full = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
        foreach (\mod_projectboard\completion\custom_completion::get_defined_custom_rules() as $rule) {
            $info->customdata["customcompletionrules"][$rule] = $full->$rule;
        }
    }
    return $info;
}

/**
 * Projectboard pluginfile for the Project Board activity.
 */
function projectboard_pluginfile($course, $cm, $context, $filearea, $args,
        $forcedownload, array $options = []) {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE ||
            !in_array($filearea, ["evidence", "commentattachment"], true)) {
        return false;
    }
    \mod_projectboard\access_manager::require_view($cm);
    $itemid = (int)array_shift($args);
    if ($filearea === "evidence") {
        $item = $DB->get_record("projectboard_evidence", ["id" => $itemid]);
    } else {
        $item = $DB->get_record("projectboard_comment", ["id" => $itemid]);
    }
    if (!$item) {
        return false;
    }
    $activity = $DB->get_record("projectboard", ["id" => $cm->instance], "*", MUST_EXIST);
    \mod_projectboard\access_manager::require_task((int)$item->taskid, $activity, $cm);
    $filename = array_pop($args);
    $filepath = "/" . implode("/", $args) . "/";
    $file = get_file_storage()->get_file($context->id, "mod_projectboard",
        $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
