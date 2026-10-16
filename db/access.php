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
 * Activity capabilities.
 *
 * @package    mod_projectboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

$capabilities = [
    "mod/projectboard:view" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:addtask" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:editown" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:editall" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:movetask" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:assign" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:managecolumns" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:comment" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:addevidence" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:review" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:grade" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:viewreports" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ],
    "mod/projectboard:manage" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "teacher" => CAP_ALLOW, "manager" => CAP_ALLOW]
    ]
];
