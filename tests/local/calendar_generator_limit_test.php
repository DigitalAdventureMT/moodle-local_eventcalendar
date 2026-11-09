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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\calendar_generator::class)]
/**
 * Tests of the maximum length of a calendar (setting maxdays).
 *
 * The calendars of the data generator last 12 days (from yesterday to 10 days from now) and have no days.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\calendar_generator
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_generator_limit_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The setting is used when it is a positive integer; otherwise the default applies.
     */
    public function test_get_max_days(): void {
        $this->assertSame(calendar_generator::DEFAULT_MAX_DAYS, calendar_generator::get_max_days());

        set_config('maxdays', 30, 'local_eventcalendar');
        $this->assertSame(30, calendar_generator::get_max_days());

        set_config('maxdays', '0', 'local_eventcalendar');
        $this->assertSame(calendar_generator::DEFAULT_MAX_DAYS, calendar_generator::get_max_days());

        set_config('maxdays', '', 'local_eventcalendar');
        $this->assertSame(calendar_generator::DEFAULT_MAX_DAYS, calendar_generator::get_max_days());
    }

    /**
     * The limit applies only when days would be created.
     */
    public function test_exceeds_max_days(): void {
        set_config('maxdays', 10, 'local_eventcalendar');

        $this->assertFalse(calendar_generator::exceeds_max_days(10, 10));
        $this->assertTrue(calendar_generator::exceeds_max_days(11, 11));
        $this->assertTrue(calendar_generator::exceeds_max_days(11, 1));
        $this->assertFalse(calendar_generator::exceeds_max_days(500, 0));
    }

    /**
     * A calendar longer than the limit gets no days, and the generator says why.
     */
    public function test_sync_extend_refuses_long_calendar(): void {
        global $DB;

        set_config('maxdays', 5, 'local_eventcalendar');
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();

        try {
            calendar_generator::sync_extend($calendar);
            $this->fail('An exception was expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errortoomanydays', $e->errorcode);
        }

        $this->assertSame(0, $DB->count_records('local_eventcalendar_days', ['calendarid' => $calendar->get('id')]));
    }

    /**
     * A calendar that became longer than the limit (setting lowered later) is accepted while no day is needed.
     */
    public function test_sync_extend_keeps_existing_long_calendar(): void {
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();
        $this->assertSame(12, calendar_generator::sync_extend($calendar));

        set_config('maxdays', 5, 'local_eventcalendar');
        $this->assertSame(0, calendar_generator::sync_extend($calendar));
    }
}
