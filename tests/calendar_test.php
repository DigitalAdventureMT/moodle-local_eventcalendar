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

namespace local_eventcalendar;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\persistent\calendar;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\persistent\calendar::class)]
/**
 * Tests for the calendar: visibility status and progressive unlock.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\persistent\calendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        access_rules::reset_cache();
    }

    /**
     * Returns the plugin test data generator.
     *
     * @return \local_eventcalendar_generator
     */
    protected function generator(): \local_eventcalendar_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
    }

    public function test_visibility_status(): void {
        $today = \local_eventcalendar_generator::utc_midnight(time());

        $visible = $this->generator()->create_calendar();
        $hidden = $this->generator()->create_calendar(['forcehide' => 1]);
        $future = $this->generator()->create_calendar(['startdate' => time() + DAYSECS]);
        $expired = $this->generator()->create_calendar(['enddisplaydate' => $today - 5 * DAYSECS]);

        $this->assertSame('visible', $visible->get_visibility_status());
        $this->assertSame('hidden_manual', $hidden->get_visibility_status());
        $this->assertSame('not_started', $future->get_visibility_status());
        $this->assertSame('expired', $expired->get_visibility_status());

        $this->assertTrue($visible->is_visible());
        $this->assertFalse($expired->is_visible());
        $this->assertTrue($hidden->is_within_active_dates());
    }

    public function test_progressive_unlock(): void {
        $tomorrow = \local_eventcalendar_generator::utc_midnight(time()) + 3 * DAYSECS;

        $progressive = $this->generator()->create_calendar();
        $free = $this->generator()->create_calendar(['progressiveunlock' => 0]);
        $lockedday = $this->generator()->create_day(['calendarid' => $progressive->get('id'), 'opendate' => $tomorrow]);
        $freeday = $this->generator()->create_day(['calendarid' => $free->get('id'), 'opendate' => $tomorrow]);

        $this->assertFalse($progressive->is_day_open($lockedday));
        $this->assertTrue($free->is_day_open($freeday));
    }
}
