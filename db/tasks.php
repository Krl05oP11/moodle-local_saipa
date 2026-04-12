<?php
// This file is part of Moodle - https://moodle.org/
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
 * Scheduled task definitions for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_saipa\task\risk_evaluation',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2', // 2am daily
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1, // Admin enables after engine is configured
    ],
    [
        'classname' => 'local_saipa\task\aggregate_daily_stats',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3', // 3am daily (after risk_evaluation at 2am)
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1, // Admin enables after engine is configured
    ],
];
