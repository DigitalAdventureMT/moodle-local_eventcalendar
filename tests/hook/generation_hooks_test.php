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

namespace local_eventcalendar\hook;

use local_eventcalendar\local\calendar_generator;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\days_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\restdays_changed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\calendar_generator::class)]
/**
 * Tests of the hooks dispatched by the generation of the days and by the realignment of the rest days.
 *
 * The calendar runs from Tuesday 1 to Monday 7 December 2026 (UTC) and uses no content pack, so the only rest
 * day on Thursday is day 3.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\hook\days_created
 * @covers      \local_eventcalendar\hook\restdays_changed
 * @covers      \local_eventcalendar\local\calendar_generator
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generation_hooks_test extends \advanced_testcase {
    /** Weekday of the rest day used by the tests: Thursday. */
    private const THURSDAY = 4;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a calendar without days, template and language, with the given number of days from 1 December 2026.
     *
     * @param int $daycount
     * @param bool $restday Whether Thursday is a weekly rest day.
     * @return calendar
     */
    protected function create_calendar(int $daycount, bool $restday): calendar {
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();
        foreach (day::get_records(['calendarid' => $calendar->get('id')]) as $existing) {
            $existing->delete();
        }

        $startdate = gmmktime(0, 0, 0, 12, 1, 2026);
        $calendar->set('templatekey', null);
        $calendar->set('language', null);
        $calendar->set('startdate', $startdate);
        $calendar->set('enddate', $startdate + (($daycount - 1) * DAYSECS));
        $calendar->set('restdayenabled', (int) $restday);
        $calendar->set('restdayweekday', self::THURSDAY);
        $calendar->update();

        return $calendar;
    }

    /**
     * The hook receives the new days in order, without pack, and the numbers of the new rest days; extending
     * the dates passes only the days added.
     */
    public function test_days_created(): void {
        $calls = [];
        $this->redirectHook(days_created::class, function (days_created $hook) use (&$calls): void {
            $calls[] = [
                'numbers' => array_map(fn (day $day): int => (int) $day->get('daynumber'), $hook->get_days()),
                'rest' => $hook->get_restdaynumbers(),
                'pack' => $hook->get_pack(),
                'calendar' => (int) $hook->get_calendar()->get('id'),
            ];
        });

        $calendar = $this->create_calendar(3, true);
        $this->assertSame(3, calendar_generator::sync_extend($calendar));

        $calendar->set('enddate', $calendar->get('enddate') + DAYSECS);
        $calendar->update();
        $this->assertSame(1, calendar_generator::sync_extend($calendar));

        // Nothing missing: no new day and no dispatch.
        $this->assertSame(0, calendar_generator::sync_extend($calendar));

        $this->assertCount(2, $calls);
        $this->assertSame([1, 2, 3], $calls[0]['numbers']);
        $this->assertSame([3], $calls[0]['rest']);
        $this->assertNull($calls[0]['pack']);
        $this->assertSame((int) $calendar->get('id'), $calls[0]['calendar']);
        $this->assertSame([4], $calls[1]['numbers']);
        $this->assertSame([], $calls[1]['rest']);
    }

    /**
     * Enabling the rest day passes the new rest day, disabling it passes the restored one; no change, no dispatch.
     */
    public function test_restdays_changed(): void {
        $calendar = $this->create_calendar(7, false);
        calendar_generator::sync_extend($calendar);

        $calls = [];
        $this->redirectHook(restdays_changed::class, function (restdays_changed $hook) use (&$calls): void {
            $numbers = fn (array $days): array => array_map(fn (day $day): int => (int) $day->get('daynumber'), $days);
            $calls[] = ['new' => $numbers($hook->get_new_restdays()), 'restored' => $numbers($hook->get_restored_days())];
        });

        $calendar->set('restdayenabled', 1);
        $calendar->update();
        $this->assertSame(1, calendar_generator::apply_restday_change($calendar, false, self::THURSDAY));

        $this->assertSame(0, calendar_generator::apply_restday_change($calendar, true, self::THURSDAY));

        $calendar->set('restdayenabled', 0);
        $calendar->update();
        $this->assertSame(1, calendar_generator::apply_restday_change($calendar, true, self::THURSDAY));

        $this->assertSame([['new' => [3], 'restored' => []], ['new' => [], 'restored' => [3]]], $calls);
    }
}
