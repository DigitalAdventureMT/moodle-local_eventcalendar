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
 * Scheduled task that sends the daily notifications to subscribed users.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\task;

use local_eventcalendar\local\notification_dispatcher;

/**
 * Scheduled task that sends the daily notifications to subscribed users.
 *
 * A thin wrapper: the logic is in \local_eventcalendar\local\notification_dispatcher,
 * which receives the current time. The frequency is set in db/tasks.php.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notifications extends \core\task\scheduled_task {
    /**
     * Returns the scheduled task name.
     *
     * @return string Name shown in the scheduled tasks list.
     */
    public function get_name(): string {
        return get_string('tasksendnotifications', 'local_eventcalendar');
    }

    /**
     * Runs the scheduled task.
     *
     * @return void
     */
    public function execute() {
        $sent = notification_dispatcher::dispatch(time());

        mtrace('local_eventcalendar: notifications sent: ' . $sent);
    }
}
