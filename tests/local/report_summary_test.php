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

#[\PHPUnit\Framework\Attributes\CoversClass(report_summary::class)]
/**
 * Tests of the figures of the calendar reports page.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\report_summary
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_summary_test extends \advanced_testcase {
    /**
     * Counts of subscribers, visitors, openings, notifications and days, for all calendars or for one.
     */
    public function test_figures(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['name' => 'Advent']);
        $calendarid = (int) $calendar->get('id');
        $day1 = $generator->create_day(['calendarid' => $calendarid]);
        $day2 = $generator->create_day(['calendarid' => $calendarid]);
        $empty = $generator->create_calendar(['name' => 'Empty']);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        subscription::subscribe($calendarid, (int) $user1->id);

        foreach ([[$day1, $user1], [$day2, $user1], [$day1, $user2]] as [$day, $user]) {
            $DB->insert_record('local_eventcalendar_log', (object) [
                'calendarid' => $calendarid, 'dayid' => $day->get('id'), 'userid' => $user->id, 'timeopened' => time(),
            ]);
        }
        foreach ([[$day1, $user1, time(), 1], [$day2, $user1, null, 2]] as [$day, $user, $timesent, $attempts]) {
            $DB->insert_record('local_eventcalendar_sent', (object) [
                'calendarid' => $calendarid, 'dayid' => $day->get('id'), 'userid' => $user->id,
                'timesent' => $timesent, 'attempts' => $attempts,
            ]);
        }

        $this->assertCount(2, report_summary::get_calendars());

        $totals = report_summary::get_calendars($calendarid)[0];
        $this->assertEquals(1, $totals->subscribers);
        $this->assertEquals(2, $totals->visitors);
        $this->assertEquals(3, $totals->openings);
        $this->assertEquals(1, $totals->sent);
        $this->assertEquals(1, $totals->notsent);
        $this->assertEquals(2, $totals->totaldays);

        $this->assertEquals(0, report_summary::get_calendars((int) $empty->get('id'))[0]->openings);
    }
}
