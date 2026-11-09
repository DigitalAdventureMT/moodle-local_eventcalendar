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

namespace local_eventcalendar\local;

use local_eventcalendar\hook\access_rule_types;
use local_eventcalendar\local\accessrule\registry;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/eventcalendar/tests/fixtures/department_rule.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\notification_dispatcher::class)]
/**
 * Tests of the sending queue of the notification task: order across calendars, limit per run, complete user
 * records for the access rules of the add-ons, and volume.
 *
 * They describe the behaviour that the sending must keep whatever the way the queue is built in memory.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\notification_dispatcher
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_queue_test extends \advanced_testcase {
    /** @var int Today's midnight in UTC. */
    protected int $today;

    /** @var \local_eventcalendar_generator */
    protected $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        access_rules::reset_cache();
        registry::reset();

        // The generator class is loaded by get_plugin_generator(), so it comes before its static helper.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $this->today = \local_eventcalendar_generator::utc_midnight(time());
    }

    protected function tearDown(): void {
        registry::reset();
        access_rules::reset_cache();
        parent::tearDown();
    }

    /**
     * Creates a calendar with a day today.
     *
     * @param string $name
     * @return \local_eventcalendar\persistent\calendar
     */
    protected function calendar_with_day(string $name): \local_eventcalendar\persistent\calendar {
        $calendar = $this->generator->create_calendar(['name' => $name, 'startdate' => $this->today - 5 * DAYSECS]);
        $this->generator->create_day(['calendarid' => $calendar->get('id'), 'opendate' => $this->today, 'notiftitle' => $name]);

        return $calendar;
    }

    /**
     * Creates users subscribed to a calendar ten days ago.
     *
     * @param \local_eventcalendar\persistent\calendar $calendar
     * @param int $count
     * @param array $record fields of the users
     * @return \stdClass[]
     */
    protected function subscribed_users(\local_eventcalendar\persistent\calendar $calendar, int $count, array $record = []): array {
        global $DB;

        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user(array_merge(['timezone' => 'UTC'], $record));
            subscription::subscribe((int) $calendar->get('id'), (int) $user->id);
            $users[] = $user;
        }
        $DB->set_field('local_eventcalendar_subscr', 'timesubscribed', $this->today - 10 * DAYSECS);

        return $users;
    }

    /**
     * Runs the dispatch at the given instant and returns the ids of the recipients, in sending order.
     *
     * @param int $now
     * @return int[]
     */
    protected function dispatch(int $now): array {
        $sink = $this->redirectMessages();
        notification_dispatcher::dispatch($now);
        $recipients = array_map(fn($message) => (int) $message->useridto, $sink->get_messages());
        $sink->close();

        return $recipients;
    }

    /**
     * With a limit, the earliest send times go first across all calendars, not calendar by calendar.
     *
     * The users of the second calendar are in Tokyo: at 9:00 UTC their 8:00 is ten hours old, so they come first
     * even though their calendar was created after the other one.
     */
    public function test_limit_follows_send_time_across_calendars(): void {
        set_config('maxemailsperrun', 2, 'local_eventcalendar');
        $first = $this->calendar_with_day('First');
        $second = $this->calendar_with_day('Second');
        $this->subscribed_users($first, 3);
        $tokyo = $this->subscribed_users($second, 2, ['timezone' => 'Asia/Tokyo']);

        $recipients = $this->dispatch($this->today + 9 * HOURSECS);

        $expected = array_map(fn($user) => (int) $user->id, $tokyo);
        sort($expected);
        sort($recipients);
        $this->assertSame($expected, $recipients);
    }

    /**
     * The access rules of the add-ons receive the complete user record, so a rule on a profile column works in
     * the task as on the pages.
     */
    public function test_rules_of_addons_see_the_complete_user(): void {
        $this->redirectHook(access_rule_types::class, function (access_rule_types $hook): void {
            $hook->add_type('department', \local_eventcalendar\tests\fixtures\department_rule::class);
        });
        registry::reset();

        $calendar = $this->calendar_with_day('Sales only');
        [$sales] = $this->subscribed_users($calendar, 1, ['department' => 'Sales']);
        $this->subscribed_users($calendar, 1, ['department' => 'Support']);
        $this->generator->create_access_rule([
            'calendarid' => $calendar->get('id'),
            'ruletype' => 'department',
            'subject' => 'Sales',
        ]);

        $this->assertSame([(int) $sales->id], $this->dispatch($this->today + 9 * HOURSECS));
    }

    /**
     * Many recipients spread over several runs: each one receives the notification exactly once, and the runs
     * after the last one send nothing.
     */
    public function test_volume_spread_over_runs(): void {
        set_config('maxemailsperrun', 120, 'local_eventcalendar');
        $first = $this->calendar_with_day('First');
        $second = $this->calendar_with_day('Second');
        $users = array_merge(
            $this->subscribed_users($first, 150),
            $this->subscribed_users($second, 150, ['timezone' => 'Europe/Rome'])
        );

        $all = [];
        foreach ([9, 10, 11, 12] as $hour) {
            $recipients = $this->dispatch($this->today + $hour * HOURSECS);
            $this->assertLessThanOrEqual(120, count($recipients));
            $all = array_merge($all, $recipients);
        }

        $this->assertCount(count($users), $all, 'Every subscriber once');
        $this->assertCount(count($users), array_unique($all), 'Nobody twice');
        $this->assertSame([], $this->dispatch($this->today + 13 * HOURSECS));
    }
}
