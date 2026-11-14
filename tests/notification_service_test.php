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
 * Notification access regression tests.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

use advanced_testcase;

/**
 * Ensures private task titles are sent only to currently eligible participants.
 *
 * @covers \mod_projectboard\notification_service
 * @covers \mod_projectboard\access_manager
 */
final class notification_service_test extends advanced_testcase {
    /**
     * Users removed from a group may no longer receive its task notifications.
     */
    public function test_removed_group_assignee_gets_no_notification(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $author = $generator->create_user();
        $recipient = $generator->create_user();
        $generator->enrol_user($author->id, $course->id, "student");
        $generator->enrol_user($recipient->id, $course->id, "student");
        $group = $generator->create_group(["courseid" => $course->id]);
        groups_add_member($group->id, $author->id);
        groups_add_member($group->id, $recipient->id);
        $activity = $generator->create_module("projectboard", [
            "course" => $course->id, "workmode" => "groups",
            "notifications" => 1, "groupmode" => SEPARATEGROUPS,
        ]);
        $cm = get_coursemodule_from_instance("projectboard", $activity->id);
        $this->setUser($author);
        $board = board_service::ensure($activity, 0, (int)$group->id);
        $columns = board_service::columns($activity);
        $taskid = task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Private project",
        ]);
        $DB->insert_record("projectboard_assignee", (object)[
            "taskid" => $taskid, "userid" => $recipient->id,
        ]);
        $task = $DB->get_record("projectboard_task", ["id" => $taskid], "*", MUST_EXIST);
        $sink = $this->redirectMessages();

        groups_remove_member($group->id, $recipient->id);
        $this->assertFalse(access_manager::can_be_assignee((int)$recipient->id, $board, $activity, $cm));
        notification_service::task_event($activity, $task, "comment");
        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse($DB->record_exists("projectboard_notice", [
            "taskid" => $taskid, "userid" => $recipient->id,
        ]));

        groups_add_member($group->id, $recipient->id);
        $this->assertTrue(access_manager::can_be_assignee((int)$recipient->id, $board, $activity, $cm));
        notification_service::task_event($activity, $task, "comment");
        $this->assertCount(1, $sink->get_messages());
        $this->assertTrue($DB->record_exists("projectboard_notice", [
            "taskid" => $taskid, "userid" => $recipient->id,
        ]));
    }

    /**
     * Being enrolled is insufficient to receive another user's individual task title.
     */
    public function test_individual_board_cannot_notify_another_student(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $generator->create_user();
        $other = $generator->create_user();
        $generator->enrol_user($owner->id, $course->id, "student");
        $generator->enrol_user($other->id, $course->id, "student");
        $activity = $generator->create_module("projectboard", [
            "course" => $course->id, "notifications" => 1,
        ]);
        $cm = get_coursemodule_from_instance("projectboard", $activity->id);
        $this->setUser($owner);
        $board = board_service::ensure($activity, (int)$owner->id);
        $columns = board_service::columns($activity);
        $taskid = task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Private task",
        ]);
        $task = $DB->get_record("projectboard_task", ["id" => $taskid], "*", MUST_EXIST);
        $sink = $this->redirectMessages();

        notification_service::send($activity, $task, (int)$other->id, "assignment", "test");
        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse($DB->record_exists("projectboard_notice", [
            "taskid" => $taskid, "userid" => $other->id,
        ]));
    }
}
