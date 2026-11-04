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

use local_eventcalendar\hook\calendar_deleting;
use local_eventcalendar\hook\calendar_saved;
use local_eventcalendar\hook\days_deleting;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\calendar_manager::class)]
/**
 * Tests for the service that manages calendars: saving, deletion and hooks.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\calendar_manager
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_manager_test extends \advanced_testcase {
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

    /**
     * Creates a file in a file area of the plugin.
     *
     * @param string $filearea
     * @param int $itemid
     * @param string $filename
     */
    protected function create_file(string $filearea, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_eventcalendar',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'content');
    }

    /**
     * Tells whether a file area of the plugin contains files.
     *
     * @param string $filearea
     * @param int $itemid
     * @return bool
     */
    protected function has_files(string $filearea, int $itemid): bool {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_eventcalendar',
            $filearea,
            $itemid,
            'id',
            false
        );

        return !empty($files);
    }

    /**
     * Inserts an icon set, needed by every calendar, and returns its id.
     *
     * @return int
     */
    protected function create_iconset(): int {
        global $DB;

        return (int) $DB->insert_record('local_eventcalendar_icons', (object) [
            'name' => 'Set', 'source' => 'custom', 'identifier' => 'set' . random_string(5),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Returns form data for a calendar of the given number of days.
     *
     * @param int $days Number of days of the calendar.
     * @param array $overrides Values replacing the defaults.
     * @return \stdClass
     */
    protected function form_data(int $days, array $overrides = []): \stdClass {
        $startdate = gmmktime(0, 0, 0, 12, 1, 2026);

        return (object) ($overrides + [
            'name' => 'Advent',
            'templatekey' => '',
            'language' => '',
            'intro' => '',
            'tagline' => '',
            'startdate' => $startdate,
            'enddate' => $startdate + (($days - 1) * DAYSECS),
            'enddisplaydate' => $startdate + (($days + 19) * DAYSECS),
            'progressiveunlock' => 1,
            'iconsetid' => $this->create_iconset(),
            'notificationsetid' => 0,
            'forcehide' => 1,
            'restdayenabled' => 0,
            'restdayweekday' => 0,
            'emailtemplateid' => 0,
        ]);
    }

    /**
     * A new calendar is created with its days, the empty selects are stored as null and the hook is
     * dispatched with the saved calendar.
     */
    public function test_save_creates_calendar_and_days(): void {
        $seen = [];
        $this->redirectHook(calendar_saved::class, function (calendar_saved $hook) use (&$seen): void {
            $seen[] = [
                'id' => (int) $hook->get_calendar()->get('id'),
                'isnew' => $hook->is_new(),
                'name' => $hook->get_data()->name,
                'days' => count(day::get_records(['calendarid' => $hook->get_calendar()->get('id')])),
            ];
        });

        $data = $this->form_data(3);
        $calendar = new calendar(0);
        $result = calendar_manager::save($calendar, $data);

        $calendarid = (int) $calendar->get('id');
        $this->assertGreaterThan(0, $calendarid);
        $this->assertSame(0, $result->restoverwritten);
        $this->assertCount(3, day::get_records(['calendarid' => $calendarid]));

        $saved = new calendar($calendarid);
        $this->assertSame('Advent', $saved->get('name'));
        $this->assertNull($saved->get('templatekey'));
        $this->assertNull($saved->get('language'));
        $this->assertNull($saved->get('notificationsetid'));
        $this->assertNull($saved->get('emailtemplateid'));
        $this->assertNull($saved->get('bannerid'));
        $this->assertNull($saved->get('headerfooterid'));

        // The hook arrives once, after the days exist, and the caller's data is not altered.
        $this->assertSame([['id' => $calendarid, 'isnew' => true, 'name' => 'Advent', 'days' => 3]], $seen);
        $this->assertSame('', $data->language);
    }

    /**
     * Updating a calendar changes its values, generates the missing days and deletes the days left out.
     */
    public function test_save_updates_calendar_and_aligns_days(): void {
        $calendar = new calendar(0);
        calendar_manager::save($calendar, $this->form_data(3));
        $calendarid = (int) $calendar->get('id');
        $this->assertCount(3, day::get_records(['calendarid' => $calendarid]));

        $isnew = null;
        $this->redirectHook(calendar_saved::class, function (calendar_saved $hook) use (&$isnew): void {
            $isnew = $hook->is_new();
        });

        // Extended to five days.
        $calendar = new calendar($calendarid);
        $iconsetid = (int) $calendar->get('iconsetid');
        calendar_manager::save($calendar, $this->form_data(5, ['name' => 'Christmas', 'iconsetid' => $iconsetid]));

        $this->assertFalse($isnew);
        $this->assertSame('Christmas', (new calendar($calendarid))->get('name'));
        $this->assertCount(5, day::get_records(['calendarid' => $calendarid]));

        // Shortened to two days: the days left out are deleted and announced by the days hook.
        $deleted = [];
        $this->redirectHook(days_deleting::class, function (days_deleting $hook) use (&$deleted): void {
            $deleted = $hook->get_dayids();
        });
        $calendar = new calendar($calendarid);
        calendar_manager::save($calendar, $this->form_data(2, ['iconsetid' => $iconsetid, 'confirmshrink' => 1]));

        $this->assertCount(2, day::get_records(['calendarid' => $calendarid]));
        $this->assertCount(3, $deleted);
    }

    /**
     * The gallery choices set the preloaded banner and pair; any other value leaves none. The choices reach
     * the hook with the data.
     */
    public function test_save_gallery_choices(): void {
        global $DB;

        $bannerid = (int) $DB->insert_record('local_eventcalendar_banner', (object) [
            'name' => 'Banner', 'identifier' => 'banner' . random_string(5), 'filename' => 'banner.png',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $pairid = (int) $DB->insert_record('local_eventcalendar_headfoot', (object) [
            'identifier' => 'pair' . random_string(5), 'headerfilename' => 'h.png', 'footerfilename' => 'f.png',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $choices = [];
        $this->redirectHook(calendar_saved::class, function (calendar_saved $hook) use (&$choices): void {
            $choices[] = [$hook->get_data()->bannerchoice, $hook->get_data()->headerfooterchoice];
        });

        $calendar = new calendar(0);
        calendar_manager::save($calendar, $this->form_data(1), (string) $bannerid, (string) $pairid);
        $calendarid = (int) $calendar->get('id');

        $saved = new calendar($calendarid);
        $this->assertEquals($bannerid, $saved->get('bannerid'));
        $this->assertEquals($pairid, $saved->get('headerfooterid'));

        // An option of another plugin: no preloaded element.
        $calendar = new calendar($calendarid);
        calendar_manager::save($calendar, $this->form_data(1, ['iconsetid' => $saved->get('iconsetid')]), 'custom', 'none');

        $reloaded = new calendar($calendarid);
        $this->assertNull($reloaded->get('bannerid'));
        $this->assertNull($reloaded->get('headerfooterid'));
        $this->assertSame([[(string) $bannerid, (string) $pairid], ['custom', 'none']], $choices);
    }

    /**
     * The calendar, its days and all their files are deleted.
     */
    public function test_delete_removes_calendar_days_and_files(): void {
        $calendar = $this->generator()->create_calendar();
        $calendarid = (int) $calendar->get('id');
        $day1 = $this->generator()->create_day(['calendarid' => $calendarid, 'daynumber' => 1]);
        $day2 = $this->generator()->create_day(['calendarid' => $calendarid, 'daynumber' => 2]);

        $this->create_file('dayicon', (int) $day1->get('id'), '01.png');
        $this->create_file('content', (int) $day2->get('id'), 'photo.jpg');
        calendar_manager::delete($calendar);

        $this->assertFalse(calendar::record_exists($calendarid));
        $this->assertSame([], day::get_records(['calendarid' => $calendarid]));
        $this->assertFalse($this->has_files('dayicon', (int) $day1->get('id')));
        $this->assertFalse($this->has_files('content', (int) $day2->get('id')));
    }

    /**
     * Only the deleted calendar is affected, not the others.
     */
    public function test_delete_leaves_other_calendars_untouched(): void {
        $deleted = $this->generator()->create_calendar();
        $kept = $this->generator()->create_calendar();
        $keptid = (int) $kept->get('id');
        $keptday = $this->generator()->create_day(['calendarid' => $keptid, 'daynumber' => 1]);
        $this->create_file('dayicon', (int) $keptday->get('id'), '01.png');

        calendar_manager::delete($deleted);

        $this->assertTrue(calendar::record_exists($keptid));
        $this->assertCount(1, day::get_records(['calendarid' => $keptid]));
        $this->assertTrue($this->has_files('dayicon', (int) $keptday->get('id')));
    }

    /**
     * The hooks are dispatched before anything is removed, with the right ids.
     */
    public function test_delete_dispatches_hooks_before_removal(): void {
        $calendar = $this->generator()->create_calendar();
        $calendarid = (int) $calendar->get('id');
        $day1 = $this->generator()->create_day(['calendarid' => $calendarid, 'daynumber' => 1]);
        $day2 = $this->generator()->create_day(['calendarid' => $calendarid, 'daynumber' => 2]);

        $seen = [];
        $this->redirectHook(calendar_deleting::class, function (calendar_deleting $hook) use (&$seen): void {
            $seen['calendar'] = [
                'id' => $hook->get_calendarid(),
                'calendarexists' => calendar::record_exists($hook->get_calendarid()),
                'days' => count(day::get_records(['calendarid' => $hook->get_calendarid()])),
            ];
        });
        $this->redirectHook(days_deleting::class, function (days_deleting $hook) use (&$seen): void {
            $seen['dayids'] = $hook->get_dayids();
            $seen['daysexist'] = count(day::get_records());
        });

        calendar_manager::delete($calendar);

        $this->assertSame($calendarid, $seen['calendar']['id']);
        $this->assertTrue($seen['calendar']['calendarexists']);
        $this->assertSame(2, $seen['calendar']['days']);
        $this->assertEqualsCanonicalizing([(int) $day1->get('id'), (int) $day2->get('id')], $seen['dayids']);
        $this->assertSame(2, $seen['daysexist']);
    }

    /**
     * The days hook is dispatched also when days are deleted on their own, and not when there are none.
     */
    public function test_delete_days_dispatches_hook_only_with_days(): void {
        $calendar = $this->generator()->create_calendar();
        $day = $this->generator()->create_day(['calendarid' => $calendar->get('id'), 'daynumber' => 1]);

        $calls = [];
        $this->redirectHook(days_deleting::class, function (days_deleting $hook) use (&$calls): void {
            $calls[] = $hook->get_dayids();
        });

        calendar_generator::delete_days([]);
        $this->assertSame([], $calls);

        // The persistent::delete() method resets the id of the object to 0, so the id is read before the deletion.
        $dayid = (int) $day->get('id');
        calendar_generator::delete_days([$day]);
        $this->assertSame([[$dayid]], $calls);
        $this->assertFalse(day::record_exists($dayid));
    }
}
