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
 * Scheduled task that alerts calendar managers about invalid access rules.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\task;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\persistent\calendar;

/**
 * Scheduled task that alerts calendar managers about access rules that are no longer valid.
 *
 * Nothing is sent when no calendar has invalid rules. Otherwise one message per recipient lists the
 * calendars to check, with a link to each rules page. The alert repeats on every run until fixed.
 *
 * Recipients are active users with local/eventcalendar:managecalendars at system level plus site admins
 * (admins have every capability but not through a role assignment, so get_users_by_capability()
 * may not return them).
 *
 * Delivered through the "invalidaccessrules" message provider (db/messages.php), in each recipient's language.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_access_rules extends \core\task\scheduled_task {
    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcheckaccessrules', 'local_eventcalendar');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        $counts = access_rules::get_invalid_counts();

        if (empty($counts)) {
            mtrace('local_eventcalendar: no invalid access rules.');
            return;
        }

        // Load the involved calendars, skipping any deleted in the meantime.
        $calendars = [];
        foreach (array_keys($counts) as $calendarid) {
            if (calendar::record_exists($calendarid)) {
                $calendars[$calendarid] = new calendar($calendarid);
            }
        }

        if (empty($calendars)) {
            return;
        }

        $sent = 0;
        foreach ($this->get_recipients() as $recipient) {
            if ($this->send($recipient, $calendars, $counts)) {
                $sent++;
            }
        }

        mtrace('local_eventcalendar: calendars with rules to check: ' . count($calendars)
            . ', alerts sent: ' . $sent);
    }

    /**
     * Returns active users who can manage calendars plus site admins, without duplicates.
     *
     * @return \stdClass[]
     */
    protected function get_recipients(): array {
        $users = get_users_by_capability(\context_system::instance(), 'local/eventcalendar:managecalendars');

        foreach (get_admins() as $admin) {
            $users[$admin->id] = $admin;
        }

        return array_filter($users, function ($user) {
            return empty($user->deleted) && empty($user->suspended);
        });
    }

    /**
     * Sends the alert to a recipient, in their language.
     *
     * @param \stdClass $recipient
     * @param calendar[] $calendars [calendarid => calendar]
     * @param array $counts [calendarid => number of invalid rules]
     * @return bool
     */
    protected function send(\stdClass $recipient, array $calendars, array $counts): bool {
        $recipient = \core_user::get_user($recipient->id);
        if (!$recipient) {
            return false;
        }

        $previouslang = force_current_language($recipient->lang ?? '');

        try {
            $context = \context_system::instance();
            $items = [];
            $plainitems = [];

            foreach ($calendars as $calendarid => $calendar) {
                $url = new \moodle_url('/local/eventcalendar/accessrules.php', ['calendarid' => $calendarid]);
                $a = (object) [
                    'name' => format_string($calendar->get('name'), true, ['context' => $context]),
                    'count' => $counts[$calendarid],
                ];
                $text = get_string('invalidrulesitem', 'local_eventcalendar', $a);

                $items[] = \html_writer::tag('li', \html_writer::link($url, $text));
                $plainitems[] = '- ' . $text . ': ' . $url->out(false);
            }

            $intro = get_string('invalidrulesbody', 'local_eventcalendar');

            $message = new \core\message\message();
            $message->courseid = SITEID;
            $message->component = 'local_eventcalendar';
            $message->name = 'invalidaccessrules';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = get_string('invalidrulessubject', 'local_eventcalendar');
            $message->fullmessage = $intro . "\n\n" . implode("\n", $plainitems);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', $intro) . \html_writer::tag('ul', implode('', $items));
            $message->smallmessage = get_string('invalidrulessubject', 'local_eventcalendar');
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/local/eventcalendar/manage.php'))->out(false);
            $message->contexturlname = get_string('manage_calendars', 'local_eventcalendar');

            return (bool) message_send($message);
        } finally {
            force_current_language($previouslang);
        }
    }
}
