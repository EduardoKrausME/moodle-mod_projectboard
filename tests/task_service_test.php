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
 * Unit tests for project board permissions and task invariants.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_projectboard;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Guarded Moodle class.
defined("MOODLE_INTERNAL") || die();

/**
 * Tests board isolation and persistent task invariants.
 *
 * @covers \mod_projectboard\board_service
 * @covers \mod_projectboard\task_service
 * @covers \mod_projectboard\dependency_service
 * @covers \mod_projectboard\access_manager
 * @covers \mod_projectboard\checklist_service
 */
final class task_service_test extends \advanced_testcase {
    /**
     * Handles setup activity for Project Board.
     */
    private function setup_activity(string $mode = "individual"): array {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $other = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, "student");
        $generator->enrol_user($other->id, $course->id, "student");
        $activity = $generator->create_module("projectboard", [
            "course" => $course->id, "workmode" => $mode,
        ]);
        $cm = get_coursemodule_from_instance("projectboard", $activity->id, $course->id, false, MUST_EXIST);
        return [$activity, $cm, $student, $other];
    }

    /**
     * Handles test individual boards reject other students for Project Board.
     */
    public function test_individual_boards_reject_other_students(): void {
        [$activity, $cm, $student, $other] = $this->setup_activity();
        $this->setUser($student);
        $board = \mod_projectboard\board_service::ensure($activity, (int)$student->id);
        \mod_projectboard\access_manager::require_board((int)$board->id, $activity, $cm);
        $this->setUser($other);
        $this->expectException(\required_capability_exception::class);
        \mod_projectboard\access_manager::require_board((int)$board->id, $activity, $cm);
    }

    /**
     * Handles test creating and moving tasks persists order for Project Board.
     */
    public function test_creating_and_moving_tasks_persists_order(): void {
        global $DB;
        [$activity, $cm, $student] = $this->setup_activity();
        $this->setUser($student);
        $board = \mod_projectboard\board_service::ensure($activity, (int)$student->id);
        $columns = \mod_projectboard\board_service::columns($activity);
        $first = \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "First",
        ]);
        $second = \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Second",
        ]);
        $row = $DB->get_record("projectboard_task", ["id" => $second], "*", MUST_EXIST);
        \mod_projectboard\task_service::move($activity, $cm, [
            "taskid" => $second, "columnid" => $columns[0]->id, "position" => 0,
            "version" => $row->version,
        ]);
        $all = array_values($DB->get_records("projectboard_task",
            ["boardid" => $board->id, "columnid" => $columns[0]->id], "sortorder ASC"));
        $this->assertSame((int)$second, (int)$all[0]->id);
        $this->assertSame((int)$first, (int)$all[1]->id);
    }

    /**
     * Handles test dependency cycles are forbidden for Project Board.
     */
    public function test_dependency_cycles_are_forbidden(): void {
        [$activity, $cm, $student] = $this->setup_activity();
        $this->setUser($student);
        $board = \mod_projectboard\board_service::ensure($activity, (int)$student->id);
        $columns = \mod_projectboard\board_service::columns($activity);
        $a = \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "A",
        ]);
        $b = \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "B",
        ]);
        \mod_projectboard\dependency_service::add($activity, $cm, $a, $b);
        $this->expectException(\moodle_exception::class);
        \mod_projectboard\dependency_service::add($activity, $cm, $b, $a);
    }

    /**
     * Handles test wip limit is enforced for Project Board.
     */
    public function test_wip_limit_is_enforced(): void {
        [$activity, $cm, $student] = $this->setup_activity();
        $this->setUser($student);
        $board = \mod_projectboard\board_service::ensure($activity, (int)$student->id);
        $columns = \mod_projectboard\board_service::columns($activity);
        $activity->maxwip = 1;
        \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "First",
        ]);
        $this->expectException(\moodle_exception::class);
        \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Second",
        ]);
    }

    /**
     * Handles test stale versions cannot overwrite tasks for Project Board.
     */
    public function test_stale_versions_cannot_overwrite_tasks(): void {
        [$activity, $cm, $student] = $this->setup_activity();
        $this->setUser($student);
        $board = \mod_projectboard\board_service::ensure($activity, (int)$student->id);
        $columns = \mod_projectboard\board_service::columns($activity);
        $id = \mod_projectboard\task_service::create($activity, $cm, [
            "boardid" => $board->id, "columnid" => $columns[0]->id, "title" => "Original",
        ]);
        \mod_projectboard\task_service::update($activity, $cm, [
            "taskid" => $id, "version" => 1, "title" => "Changed",
        ]);
        $this->expectException(\moodle_exception::class);
        \mod_projectboard\task_service::update($activity, $cm, [
            "taskid" => $id, "version" => 1, "title" => "Stale",
        ]);
    }
}
