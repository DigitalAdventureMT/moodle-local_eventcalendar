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

namespace local_eventcalendar\task;

use local_eventcalendar\persistent\calendar;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\task\hide_expired_calendars::class)]
/**
 * Tests for the task hiding expired calendars: it runs only 24 hours after the end of the deactivation date in UTC.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\task\hide_expired_calendars
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hide_expired_calendars_test extends \advanced_testcase {
    public function test_execute(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $today = \local_eventcalendar_generator::utc_midnight(time());

        $longexpired = $generator->create_calendar(['enddisplaydate' => $today - 3 * DAYSECS]);
        $justexpired = $generator->create_calendar(['enddisplaydate' => $today - DAYSECS]);
        $active = $generator->create_calendar(['enddisplaydate' => $today + 5 * DAYSECS]);

        $task = new hide_expired_calendars();
        $this->assertNotEmpty($task->get_name());

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('1', $output);
        $this->assertTrue((bool) (new calendar($longexpired->get('id')))->get('forcehide'), 'Expired more than a day ago');
        $this->assertFalse((bool) (new calendar($justexpired->get('id')))->get('forcehide'), 'Still within the 24 hour margin');
        $this->assertFalse((bool) (new calendar($active->get('id')))->get('forcehide'));
    }
}
