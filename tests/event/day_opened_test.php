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

namespace local_eventcalendar\event;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\event\day_opened::class)]
/**
 * Tests for the day opened event.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\event\day_opened
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_opened_test extends \advanced_testcase {
    public function test_event(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $day = $generator->create_day(['calendarid' => $calendar->get('id'), 'daynumber' => 5]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $sink = $this->redirectEvents();
        day_opened::create([
            'objectid' => $day->get('id'),
            'context' => \context_system::instance(),
            'other' => ['calendarid' => $calendar->get('id'), 'daynumber' => 5],
        ])->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(day_opened::class, $event);
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals($day->get('id'), $event->objectid);
        $this->assertStringContainsString('view_day.php', $event->get_url()->out(false));
        $this->assertStringContainsString((string) $user->id, $event->get_description());
        $this->assertNotEmpty(day_opened::get_name());
    }

    public function test_missing_other_data(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        day_opened::create([
            'objectid' => 1,
            'context' => \context_system::instance(),
            'other' => ['daynumber' => 1],
        ]);
    }
}
