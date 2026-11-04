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

namespace local_eventcalendar\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_eventcalendar\local\subscription;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\privacy\provider::class)]
/**
 * Tests for the Privacy API: metadata, contexts, users, export and deletion.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\privacy\provider
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass User with usage data. */
    protected $reader;

    /** @var \stdClass Second user with usage data. */
    protected $otherreader;

    /** @var \stdClass Calendar author. */
    protected $author;

    /** @var \local_eventcalendar\persistent\calendar Test calendar. */
    protected $calendar;

    /**
     * Creates two readers (opened days, subscription, received notifications)
     * and an author of the calendar and of the day.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $generator = $dg->get_plugin_generator('local_eventcalendar');

        $this->reader = $dg->create_user();
        $this->otherreader = $dg->create_user();
        $this->author = $dg->create_user();

        $this->setUser($this->author);
        $this->calendar = $generator->create_calendar(['name' => 'Advent']);
        $day = $generator->create_day(['calendarid' => $this->calendar->get('id'), 'daynumber' => 3]);
        $this->setUser(null);

        foreach ([$this->reader, $this->otherreader] as $user) {
            subscription::subscribe((int) $this->calendar->get('id'), (int) $user->id);
            $DB->insert_record('local_eventcalendar_log', (object) [
                'calendarid' => $this->calendar->get('id'), 'dayid' => $day->get('id'),
                'userid' => $user->id, 'timeopened' => time(),
            ]);
            $DB->insert_record('local_eventcalendar_sent', (object) [
                'calendarid' => $this->calendar->get('id'), 'dayid' => $day->get('id'),
                'userid' => $user->id, 'timesent' => time(),
            ]);
        }
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_eventcalendar'));
        $names = array_map(function ($item) {
            return $item->get_name();
        }, $collection->get_collection());

        $this->assertContains('local_eventcalendar_log', $names);
        $this->assertContains('local_eventcalendar_subscr', $names);
        $this->assertContains('local_eventcalendar_sent', $names);
        $this->assertContains('local_eventcalendar_calendar', $names);
        $this->assertContains('core_message', $names);
    }

    public function test_get_contexts_for_userid(): void {
        $system = \context_system::instance();
        $nobody = $this->getDataGenerator()->create_user();

        $this->assertEquals([$system->id], provider::get_contexts_for_userid($this->reader->id)->get_contextids());
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($this->author->id)->get_contextids());
        $this->assertEquals([], provider::get_contexts_for_userid($nobody->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $userlist = new userlist(\context_system::instance(), 'local_eventcalendar');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [$this->reader->id, $this->otherreader->id, $this->author->id];
        sort($expected);

        $this->assertEquals($expected, $userids);
    }

    public function test_export_user_data(): void {
        $system = \context_system::instance();
        $root = get_string('pluginname', 'local_eventcalendar');

        $this->export_context_data_for_user($this->reader->id, $system, 'local_eventcalendar');
        $data = writer::with_context($system)->get_data([$root, 'Advent (' . $this->calendar->get('id') . ')']);

        $this->assertNotEmpty($data->subscription);
        $this->assertCount(1, $data->openeddays);
        $this->assertSame(3, $data->openeddays[0]['daynumber']);
        $this->assertCount(1, $data->receivednotifications);

        writer::reset();
        $this->export_context_data_for_user($this->author->id, $system, 'local_eventcalendar');
        $authored = writer::with_context($system)->get_data([$root, get_string('privacy:authoreditems', 'local_eventcalendar')]);
        $tables = array_column($authored->items, 'table');
        $this->assertContains('local_eventcalendar_calendar', $tables);
        $this->assertContains('local_eventcalendar_days', $tables);
    }

    /**
     * A notification not sent is exported with the "not sent" text instead of the time of sending,
     * together with the number of attempts.
     */
    public function test_export_notification_not_sent(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $day = $generator->create_day(['calendarid' => $this->calendar->get('id'), 'daynumber' => 4]);
        $DB->insert_record('local_eventcalendar_sent', (object) [
            'calendarid' => $this->calendar->get('id'), 'dayid' => $day->get('id'),
            'userid' => $this->reader->id, 'timesent' => null, 'attempts' => 2,
        ]);

        $system = \context_system::instance();
        $this->export_context_data_for_user($this->reader->id, $system, 'local_eventcalendar');
        $data = writer::with_context($system)->get_data([
            get_string('pluginname', 'local_eventcalendar'),
            'Advent (' . $this->calendar->get('id') . ')',
        ]);

        $notifications = array_column($data->receivednotifications, null, 'daynumber');
        $this->assertCount(2, $notifications);
        $this->assertSame(get_string('privacy:notsent', 'local_eventcalendar'), $notifications[4]['timesent']);
        $this->assertSame(2, $notifications[4]['attempts']);
        $this->assertSame(1, $notifications[3]['attempts']);
        $this->assertNotSame(get_string('privacy:notsent', 'local_eventcalendar'), $notifications[3]['timesent']);
    }

    public function test_delete_data_for_user(): void {
        global $DB;

        $contextlist = new approved_contextlist($this->reader, 'local_eventcalendar', [\context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        foreach (['local_eventcalendar_log', 'local_eventcalendar_subscr', 'local_eventcalendar_sent'] as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $this->reader->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $this->otherreader->id]), $table);
        }
    }

    public function test_delete_data_for_users_anonymises_authored_items(): void {
        global $DB;

        $userlist = new approved_userlist(\context_system::instance(), 'local_eventcalendar', [$this->author->id]);
        provider::delete_data_for_users($userlist);

        $calendar = $DB->get_record('local_eventcalendar_calendar', ['id' => $this->calendar->get('id')]);
        $this->assertNotFalse($calendar, 'The calendar is kept');
        $this->assertEquals(0, $calendar->usercreated);
        $this->assertEquals(0, $calendar->usermodified);
        $this->assertFalse($DB->record_exists('local_eventcalendar_days', ['usercreated' => $this->author->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $DB->count_records('local_eventcalendar_log'));
        $this->assertSame(0, $DB->count_records('local_eventcalendar_subscr'));
        $this->assertSame(0, $DB->count_records('local_eventcalendar_sent'));
        $this->assertTrue($DB->record_exists('local_eventcalendar_calendar', ['id' => $this->calendar->get('id')]));
        $this->assertFalse($DB->record_exists('local_eventcalendar_calendar', ['usercreated' => $this->author->id]));
    }

    public function test_other_contexts_ignored(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertSame(2, $DB->count_records('local_eventcalendar_log'));
    }
}
