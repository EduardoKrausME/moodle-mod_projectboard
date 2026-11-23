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
 * Privacy export and erasure regressions.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

use advanced_testcase;
use context_module;
use core_privacy\local\request\writer;
use core_privacy\tests\request\approved_contextlist;
use mod_projectboard\privacy\provider;

/**
 * Tests that graders and comment attachments are covered by privacy requests.
 *
 * @covers \mod_projectboard\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /**
     * Grades and scores given by a person must be included in their export.
     */
    public function test_export_and_erase_grader_attribution(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $learner = $generator->create_user();
        $grader = $generator->create_user();
        $activity = $generator->create_module("projectboard", ["course" => $course->id]);
        $cm = get_coursemodule_from_instance("projectboard", $activity->id);
        $context = context_module::instance($cm->id);
        $board = board_service::ensure($activity, (int)$learner->id);
        $gradeid = $DB->insert_record("projectboard_grade", (object)[
            "projectboardid" => $activity->id, "boardid" => $board->id,
            "userid" => $learner->id, "gradedby" => $grader->id,
            "grade" => 75, "feedback" => "Reviewed", "feedbackformat" => FORMAT_PLAIN,
            "timecreated" => time(), "timemodified" => time(),
        ]);
        $criterionid = $DB->insert_record("projectboard_criterion", (object)[
            "projectboardid" => $activity->id, "name" => "Quality",
            "maxscore" => 10, "sortorder" => 0,
        ]);
        $scoreid = $DB->insert_record("projectboard_score", (object)[
            "criterionid" => $criterionid, "boardid" => $board->id,
            "userid" => $learner->id, "gradedby" => $grader->id,
            "score" => 8, "timemodified" => time(),
        ]);

        // Database drivers may return context IDs as strings, so normalize them before comparing.
        $contextids = provider::get_contexts_for_userid((int)$grader->id)->get_contextids();
        $this->assertContains((int)$context->id, array_map("intval", $contextids));
        $approved = new approved_contextlist($grader, "mod_projectboard", [$context->id]);
        provider::export_user_data($approved);
        $data = writer::with_context($context)->get_data([get_string("pluginname", "projectboard")]);
        $this->assertCount(1, $data->gradesgiven);
        $this->assertCount(1, $data->criteriagiven);
        $this->assertEquals($gradeid, $data->gradesgiven[0]->id);
        $this->assertEquals($scoreid, $data->criteriagiven[0]->id);

        provider::delete_data_for_user($approved);
        $this->assertEquals(0, $DB->get_field("projectboard_grade", "gradedby", ["id" => $gradeid]));
        $this->assertEquals(0, $DB->get_field("projectboard_score", "gradedby", ["id" => $scoreid]));
        $this->assertTrue($DB->record_exists("projectboard_grade", ["id" => $gradeid]));
        $this->assertTrue($DB->record_exists("projectboard_score", ["id" => $scoreid]));
    }

    /**
     * Comment attachments must be exported for, and removed with, their author.
     */
    public function test_comment_attachment_export_and_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, "student");
        $activity = $generator->create_module("projectboard", ["course" => $course->id]);
        $cm = get_coursemodule_from_instance("projectboard", $activity->id);
        $context = context_module::instance($cm->id);
        $this->setUser($learner);
        $board = board_service::ensure($activity, (int)$learner->id);
        $columns = board_service::columns($activity);
        $taskid = task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Task",
        ]);
        $commentid = comment_service::add($activity, $cm, [
            "taskid" => $taskid, "content" => "Attached report",
        ]);
        get_file_storage()->create_file_from_string((object)[
            "contextid" => $context->id, "component" => "mod_projectboard",
            "filearea" => "commentattachment", "itemid" => $commentid,
            "filepath" => "/", "filename" => "report.txt", "userid" => $learner->id,
        ], "Project report");

        $approved = new approved_contextlist($learner, "mod_projectboard", [$context->id]);
        provider::export_user_data($approved);
        $files = writer::with_context($context)->get_files([
            get_string("comment", "projectboard"), (string)$commentid,
        ]);
        $this->assertCount(1, $files);

        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists("projectboard_comment", ["id" => $commentid]));
        $this->assertFalse(get_file_storage()->get_file(
            $context->id, "mod_projectboard", "commentattachment", $commentid, "/", "report.txt"
        ));
    }
}
