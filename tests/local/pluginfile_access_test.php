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

#[\PHPUnit\Framework\Attributes\CoversClass(pluginfile_access::class)]
/**
 * Tests of the access rules of the files served by the pluginfile callbacks.
 *
 * Each test builds a calendar with an unlocked and a locked day, then checks what a regular user and a
 * manager of the content can read. The rules mirror view.php and view_day.php: calendar visible, access
 * rules satisfied and, for the content only, day unlocked.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\pluginfile_access
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pluginfile_access_test extends \advanced_testcase {
    /** @var \local_eventcalendar_generator */
    protected $generator;

    /** @var \local_eventcalendar\persistent\calendar */
    protected $calendar;

    /** @var int Id of a day already unlocked. */
    protected $openday;

    /** @var int Id of a day unlocked in the future. */
    protected $lockedday;

    /**
     * Creates a visible calendar with an unlocked and a locked day, and logs in a regular user.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $this->calendar = $this->generator->create_calendar();
        $calendarid = (int) $this->calendar->get('id');

        $this->openday = (int) $this->generator->create_day(['calendarid' => $calendarid, 'daynumber' => 1])->get('id');
        $this->lockedday = (int) $this->generator->create_day([
            'calendarid' => $calendarid,
            'daynumber' => 2,
            'opendate' => $this->calendar->get('startdate') + 6 * DAYSECS,
        ])->get('id');

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * A regular user reads the files of a visible calendar; the content of a locked day waits for the unlock,
     * while its icon is readable because the grid shows it.
     */
    public function test_visible_calendar(): void {
        $calendarid = (int) $this->calendar->get('id');

        $this->assertTrue(pluginfile_access::can_view_calendar_files($calendarid));
        $this->assertTrue(pluginfile_access::can_view_day_file($this->openday, 'content'));
        $this->assertTrue(pluginfile_access::can_view_day_file($this->openday, 'dayicon'));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->lockedday, 'content'));
        $this->assertTrue(pluginfile_access::can_view_day_file($this->lockedday, 'dayicon'));
    }

    /**
     * Without progressive unlock every day of a visible calendar is readable.
     */
    public function test_without_progressive_unlock(): void {
        $this->calendar->set('progressiveunlock', 0);
        $this->calendar->update();

        $this->assertTrue(pluginfile_access::can_view_day_file($this->lockedday, 'content'));
    }

    /**
     * A hidden calendar hides all its files from a regular user.
     */
    public function test_hidden_calendar(): void {
        $this->calendar->set('forcehide', 1);
        $this->calendar->update();

        $this->assertFalse(pluginfile_access::can_view_calendar_files((int) $this->calendar->get('id')));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->openday, 'content'));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->openday, 'dayicon'));
    }

    /**
     * A user who does not satisfy the access rules is treated as for a hidden calendar.
     */
    public function test_access_rules_not_satisfied(): void {
        global $DB;

        access_rules::save((int) $this->calendar->get('id'), [
            ['rules' => [['ruletype' => 'role', 'subject' => (string) $DB->get_field('role', 'id', ['shortname' => 'manager']),
                'comparison' => 'is', 'value' => '']]],
        ]);

        $this->assertFalse(pluginfile_access::can_view_calendar_files((int) $this->calendar->get('id')));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->openday, 'content'));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->openday, 'dayicon'));
    }

    /**
     * Managers of the content read everything, as on the pages, but not what does not exist.
     */
    public function test_manager_bypass(): void {
        $this->calendar->set('forcehide', 1);
        $this->calendar->update();
        $this->setAdminUser();

        $this->assertTrue(pluginfile_access::can_view_calendar_files((int) $this->calendar->get('id')));
        $this->assertTrue(pluginfile_access::can_view_day_file($this->lockedday, 'content'));
    }

    /**
     * Ids of missing calendars and days give no access.
     */
    public function test_missing_items(): void {
        $this->assertFalse(pluginfile_access::can_view_calendar_files((int) $this->calendar->get('id') + 1000));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->lockedday + 1000, 'content'));
        $this->assertFalse(pluginfile_access::can_view_day_file($this->lockedday + 1000, 'dayicon'));
    }

    /**
     * Only images are shown inline: HTML pages and other files are downloaded.
     */
    public function test_must_force_download(): void {
        $fs = get_file_storage();
        $record = [
            'contextid' => \context_system::instance()->id, 'component' => 'local_eventcalendar',
            'filearea' => 'content', 'itemid' => $this->openday, 'filepath' => '/',
        ];

        $expected = ['photo.png' => false, 'photo.jpg' => false, 'icon.svg' => false,
            'page.html' => true, 'page.htm' => true, 'doc.pdf' => true, 'script.js' => true];
        foreach ($expected as $filename => $download) {
            $file = $fs->create_file_from_string($record + ['filename' => $filename], 'x');
            $this->assertSame($download, pluginfile_access::must_force_download($file), $filename);
        }
    }
}
