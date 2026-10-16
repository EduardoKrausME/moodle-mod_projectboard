# Project Board for Moodle

Project Board is a course activity for organising academic projects as individual, Moodle-group, or shared Kanban boards. The activity stores task state on the Moodle server and applies module capabilities and group restrictions to write operations.

## Requirements

Moodle 4.5 LTS or Moodle 5.x. Install under `mod/projectboard`. The initial release metadata declares version `2026101500`, release `1.0.0`, and beta maturity until real Moodle integration tests pass.

## Main components

- `db/install.xml`: Moodle XMLDB schema for project boards, columns, transitions, tasks, assignments, checklists, dependencies, comments, evidence, functional history, assessments, and deduplicated notifications.
- `classes/access_manager.php`: module capabilities, individual and Moodle-group board isolation.
- `classes/task_service.php`, `column_service.php`, `dependency_service.php`, `checklist_service.php`: persistent commands using Moodle database transactions and Lock API.
- `classes/external/operation.php`: authenticated AJAX controller that delegates to domain services.
- `templates/*.mustache`, `classes/presentation/board_data.php`, `amd/src/board.js`: server-rendered Kanban with keyboard alternatives.
- `classes/grading_service.php`, `criterion_service.php`, `completion_service.php`: Moodle gradebook and completion integration.
- `classes/privacy/provider.php`, `backup/moodle2`: Privacy API and backup/restore.
- `classes/task/deadline_notifications.php`: scheduled reminder delivery.

## Permissions

Module capabilities are declared under `db/access.php`. Teachers can manage boards, transitions, submissions, and grades. Student writes are constrained to boards they can modify; visible groups permit observation without modification.

## Quality gates

The repository provides PHPUnit fixtures in `tests/` and an initial Behat scenario in `tests/behat/`. Validate Moodle 4.5 and 5.x with the project's existing CI. Run the official validator from a complete checkout of `EduardoKrausME/moodle-plugin-validate` using `python3 .chatgpt/run.py --check /absolute/path/to/mod/projectboard`. The language normalizer currently conflicts with the mandatory double-quote convention, so `--check` does not by itself establish compliance with both requirements.

The source tree may contain functionality needing additional integration testing; do not deploy as production-ready based on repository contents alone.
