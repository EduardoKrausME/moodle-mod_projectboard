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
 * Language strings.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['addcheck'] = 'Add checklist item';
$string['addcolumn'] = 'New column';
$string['addcomment'] = 'Post comment';
$string['adddependency'] = 'Add dependency by task ID';
$string['addevidence'] = 'Upload evidence';
$string['addtask'] = 'New task';
$string['all'] = 'All';
$string['allowassign'] = 'Allow learners to assign tasks';
$string['allowcreate'] = 'Allow learners to create tasks';
$string['allowmove'] = 'Allow learners to move tasks';
$string['approve'] = 'Approve';
$string['archive'] = 'Archive';
$string['archivecolumn'] = 'Archive column';
$string['assign'] = 'Assign';
$string['assignees'] = 'Assignee user IDs (comma separated)';
$string['backlog'] = 'Backlog';
$string['blocked'] = 'Blocked by dependencies';
$string['board'] = 'Board';
$string['boards'] = 'Boards';
$string['cancel'] = 'Cancel';
$string['collective'] = 'Shared class board';
$string['colour'] = 'Colour';
$string['column'] = 'Column';
$string['columncolour'] = 'Column colour';
$string['columnname'] = 'Column name';
$string['comment'] = 'Comment';
$string['commentwithfiles'] = 'Write a comment with attachments';
$string['completed'] = 'Completed';
$string['completionchecklist'] = 'Require all mandatory checklist items';
$string['completionchecklistrule'] = 'Finish mandatory checklist items';
$string['completioncreated'] = 'Minimum tasks created';
$string['completiondone'] = 'Minimum tasks completed';
$string['completiondonerule'] = 'Complete {$a} tasks';
$string['completiongraded'] = 'Require a grade';
$string['completiongraderule'] = 'Receive a grade';
$string['completionminscore'] = 'Minimum grade for completion (0 to disable)';
$string['completionminscorerule'] = 'Achieve the minimum grade';
$string['completionproject'] = 'Require participation in completed project';
$string['completionprojectrule'] = 'Participate in a completed project';
$string['completiontasksrule'] = 'Create {$a} tasks';
$string['conflict'] = 'The task has changed. Reload it before saving.';
$string['criteria'] = 'Criteria-based grading';
$string['criterionname'] = 'Grading criterion';
$string['cyclicdependency'] = 'Circular dependencies are not allowed.';
$string['dateinvalid'] = 'End date must be after the start date.';
$string['daysbefore'] = 'Deadline notification lead time (days)';
$string['description'] = 'Description';
$string['done'] = 'Done';
$string['duedate'] = 'Due date';
$string['duplicate'] = 'Duplicate';
$string['editcolumn'] = 'Edit column';
$string['emptyboard'] = 'This board has no tasks yet.';
$string['enddate'] = 'Project deadline';
$string['error'] = 'Unable to complete the request';
$string['errorinvalid'] = 'Invalid operation or request.';
$string['estimate'] = 'Effort estimate (minutes)';
$string['eventname'] = 'Event';
$string['evidence'] = 'Evidence';
$string['exportcsv'] = 'Export CSV';
$string['feedback'] = 'Assessment feedback';
$string['filter'] = 'Filter';
$string['grade'] = 'Grade';
$string['graded'] = 'Graded';
$string['grademode'] = 'Grading mode';
$string['group'] = 'Group';
$string['groups'] = 'Group boards';
$string['high'] = 'High';
$string['history'] = 'History';
$string['individual'] = 'Individual boards';
$string['individualgrade'] = 'Individual grade';
$string['inprogress'] = 'In progress';
$string['invalidaccess'] = 'You do not have permission to access this board.';
$string['invaliddate'] = 'Invalid date';
$string['invalidtransition'] = 'This task cannot be moved to that column.';
$string['itemdone'] = 'Item completed';
$string['lastmodified'] = 'Last modified';
$string['late'] = 'Overdue';
$string['loading'] = 'Loading…';
$string['low'] = 'Low';
$string['managecolumns'] = 'Manage columns';
$string['managegrade'] = 'Grade project';
$string['maxscore'] = 'Maximum criterion score';
$string['maxwip'] = 'Maximum tasks in progress (0 for unlimited)';
$string['messageprovider:assignment'] = 'Task assignment';
$string['messageprovider:comment'] = 'Task comments';
$string['messageprovider:deadline'] = 'Approaching and overdue deadlines';
$string['messageprovider:dependency'] = 'Task dependencies';
$string['messageprovider:review'] = 'Review and approval';
$string['modulename'] = 'Project Board';
$string['modulename_help'] = 'Academic project management with Kanban boards.';
$string['modulenameplural'] = 'Project Boards';
$string['move'] = 'Move';
$string['movedown'] = 'Move down';
$string['moveup'] = 'Move up';
$string['newvalue'] = 'New value';
$string['noboard'] = 'No accessible project board is available.';
$string['nocompletion'] = 'No completion criteria configured';
$string['noevidence'] = 'No evidence yet.';
$string['none'] = 'No grading';
$string['normal'] = 'Normal';
$string['notifications'] = 'Enable notifications';
$string['objective'] = 'Project objective';
$string['oldvalue'] = 'Previous value';
$string['operationfailed'] = 'The operation could not be completed.';
$string['overridelabel'] = 'Individual grade override';
$string['pending'] = 'Pending';
$string['pluginadministration'] = 'Project Board administration';
$string['pluginfile'] = 'Project Board file';
$string['pluginname'] = 'Project Board';
$string['priority'] = 'Priority';
$string['privacy:metadata:'] = 'Project Board learning activity records';
$string['privacy:metadata:projectboard_assignee'] = 'User assignments to project tasks.';
$string['privacy:metadata:projectboard_board'] = 'Personal project board ownership.';
$string['privacy:metadata:projectboard_check'] = 'Checklist responsibility and completion.';
$string['privacy:metadata:projectboard_comment'] = 'Comments and replies created by participants.';
$string['privacy:metadata:projectboard_evidence'] = 'Submitted evidence and associated files.';
$string['privacy:metadata:projectboard_grade'] = 'Project assessment and feedback.';
$string['privacy:metadata:projectboard_history'] = 'Audit history of project changes.';
$string['privacy:metadata:projectboard_notice'] = 'Notifications already delivered.';
$string['privacy:metadata:projectboard_score'] = 'Assessment criterion scores.';
$string['privacy:metadata:projectboard_task'] = 'Stored tasks, author information, and project work.';
$string['project'] = 'Project grade';
$string['projectboard:addevidence'] = 'Add evidence to project tasks';
$string['projectboard:addinstance'] = 'Add a Project Board activity';
$string['projectboard:addtask'] = 'Add project tasks';
$string['projectboard:assign'] = 'Assign project tasks';
$string['projectboard:comment'] = 'Comment on project tasks';
$string['projectboard:editall'] = 'Edit all project tasks';
$string['projectboard:editown'] = 'Edit own project tasks';
$string['projectboard:grade'] = 'Grade project tasks';
$string['projectboard:manage'] = 'Manage Project Boards';
$string['projectboard:managecolumns'] = 'Manage Project Board columns';
$string['projectboard:movetask'] = 'Move project tasks';
$string['projectboard:review'] = 'Review project tasks';
$string['projectboard:view'] = 'View Project Boards';
$string['projectboard:viewreports'] = 'View Project Board reports';
$string['projectboardname'] = 'Project Board name';
$string['reopen'] = 'Reopen';
$string['report'] = 'Teacher report';
$string['requireevidence'] = 'Require evidence before completion';
$string['requiresfiles'] = 'This task requires evidence before completion.';
$string['responsible'] = 'Responsible';
$string['returnreview'] = 'Return for corrections';
$string['review'] = 'Review';
$string['reviewcomment'] = 'Review feedback';
$string['save'] = 'Save';
$string['search'] = 'Search tasks';
$string['selectboard'] = 'Choose board';
$string['sendreview'] = 'Send to review';
$string['settings'] = 'Settings';
$string['showhistory'] = 'Allow learners to view task history';
$string['start'] = 'Start date';
$string['startdate'] = 'Project start';
$string['status'] = 'Status';
$string['submit'] = 'Submit';
$string['subtask'] = 'Create subtask';
$string['tags'] = 'Tags (comma separated)';
$string['task_deadlines'] = 'Send project task deadline notifications';
$string['taskchecklist'] = 'Checklist';
$string['taskdetails'] = 'Task details';
$string['taskedit'] = 'Edit task';
$string['taskid'] = 'Task ID';
$string['tasklimit'] = 'The WIP limit for this column has been reached.';
$string['tasknotification'] = 'Project Board notification';
$string['tasks'] = 'Tasks';
$string['tasktitle'] = 'Task title';
$string['timecreated'] = 'Created';
$string['todo'] = 'To do';
$string['transition'] = 'Allowed transitions';
$string['unassigned'] = 'Unassigned';
$string['urgent'] = 'Urgent';
$string['wiplimit'] = 'WIP limit';
$string['workmode'] = 'Collaboration mode';
