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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\day_opening_log::class)]
/**
 * Tests of the opening log: one row per user and day, and true only for the call that wrote it.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\day_opening_log
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_opening_log_test extends \advanced_testcase {
    /** @var int calendar id */
    private int $calendarid;

    /** @var int day id */
    private int $dayid;

    /** @var int user id */
    private int $userid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $this->calendarid = (int) $calendar->get('id');
        $this->dayid = (int) $generator->create_day(['calendarid' => $this->calendarid, 'daynumber' => 1])->get('id');
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
    }

    /**
     * Only the first call writes the row and returns true; the following calls change nothing.
     */
    public function test_first_opening_only(): void {
        global $DB;

        $this->assertTrue(day_opening_log::record_first_opening($this->calendarid, $this->dayid, $this->userid));
        $this->assertFalse(day_opening_log::record_first_opening($this->calendarid, $this->dayid, $this->userid));

        $this->assertSame(1, $DB->count_records('local_eventcalendar_log', [
            'dayid' => $this->dayid,
            'userid' => $this->userid,
        ]));
    }

    /**
     * Another user opening the same day gets a row of their own.
     */
    public function test_other_user(): void {
        global $DB;

        $otheruserid = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertTrue(day_opening_log::record_first_opening($this->calendarid, $this->dayid, $this->userid));
        $this->assertTrue(day_opening_log::record_first_opening($this->calendarid, $this->dayid, $otheruserid));

        $this->assertSame(2, $DB->count_records('local_eventcalendar_log', ['dayid' => $this->dayid]));
    }

    /**
     * The unique index refuses a second row for the same user and day, whoever writes it: this is what
     * resolves two concurrent first openings.
     */
    public function test_unique_index(): void {
        global $DB;

        $record = (object) [
            'calendarid' => $this->calendarid,
            'dayid' => $this->dayid,
            'userid' => $this->userid,
            'timeopened' => time(),
        ];
        $DB->insert_record('local_eventcalendar_log', $record);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_eventcalendar_log', $record);
    }
}
