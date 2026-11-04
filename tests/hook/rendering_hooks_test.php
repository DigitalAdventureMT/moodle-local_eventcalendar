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

use local_eventcalendar\local\daycontent_renderer;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\iconitem;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\day_cta::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\image_url::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\iconitem_url::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\iconitem_file::class)]
/**
 * Tests of the hooks for the call to action, the images of the calendars and the icons of custom sets.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\hook\day_cta
 * @covers      \local_eventcalendar\hook\image_url
 * @covers      \local_eventcalendar\hook\iconitem_url
 * @covers      \local_eventcalendar\hook\iconitem_file
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rendering_hooks_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
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
     * The HTML set by a callback replaces the call to action, and the callback receives the overrides.
     */
    public function test_day_cta_replaces_button(): void {
        $calendar = $this->generator()->create_calendar();
        $day = $this->generator()->create_day(['calendarid' => $calendar->get('id'), 'daynumber' => 1]);

        $seen = null;
        $this->redirectHook(day_cta::class, function (day_cta $hook) use (&$seen): void {
            $seen = $hook->get_overrides();
            $hook->set_html('<span class="extra-cta">Go</span>');
        });

        $html = daycontent_renderer::render($day, new calendar($calendar->get('id')), ['extrafield' => 'Typed']);

        $this->assertStringContainsString('extra-cta', $html);
        $this->assertSame(['extrafield' => 'Typed'], $seen);
    }

    /**
     * Without a preloaded banner the address comes from the callback; the area is passed to it.
     */
    public function test_image_url_provides_banner(): void {
        $calendar = new calendar($this->generator()->create_calendar(['bannerid' => null, 'headerfooterid' => null])->get('id'));
        $this->assertNull($calendar->get_banner_url());

        $areas = [];
        $this->redirectHook(image_url::class, function (image_url $hook) use (&$areas): void {
            $areas[] = $hook->get_area();
            if ($hook->get_area() === 'banner') {
                $hook->set_url(new \moodle_url('/extra/banner.png'));
            }
        });

        $this->assertSame('/extra/banner.png', $calendar->get_banner_url()->out_as_local_url(false));
        $this->assertNull($calendar->get_header_url());
        $this->assertSame(['banner', 'headerimage'], $areas);
    }

    /**
     * The address of an icon of a custom set can be replaced; the callback receives the icon.
     */
    public function test_iconitem_url_custom_set(): void {
        global $DB;

        $setid = $DB->insert_record('local_eventcalendar_icons', (object) [
            'name' => 'Set', 'source' => 'custom', 'identifier' => 'set' . random_string(5),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $icon = new iconitem(0, (object) ['iconsetid' => $setid, 'filename' => 'star.svg']);
        $icon->create();

        $this->assertStringContainsString('/iconitem/', $icon->get_url()->out(false));

        $seenid = null;
        $this->redirectHook(iconitem_url::class, function (iconitem_url $hook) use (&$seenid): void {
            $seenid = (int) $hook->get_iconitem()->get('id');
            $hook->set_url(new \moodle_url('/extra/star.svg'));
        });

        $this->assertSame('/extra/star.svg', $icon->get_url()->out_as_local_url(false));
        $this->assertSame((int) $icon->get('id'), $seenid);
    }

    /**
     * The icon of a custom set is copied to the day from the file given by the callback; without a file the
     * day has no icon.
     */
    public function test_iconitem_file_custom_set(): void {
        global $DB;

        $setid = $DB->insert_record('local_eventcalendar_icons', (object) [
            'name' => 'Set', 'source' => 'custom', 'identifier' => 'set' . random_string(5),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $icon = new iconitem(0, (object) ['iconsetid' => $setid, 'filename' => 'star.svg']);
        $icon->create();

        $contextid = \context_system::instance()->id;
        $fs = get_file_storage();
        $source = $fs->create_file_from_string([
            'contextid' => $contextid, 'component' => 'local_eventcalendar', 'filearea' => 'testicon',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'star.svg',
        ], '<svg></svg>');

        $this->redirectHook(iconitem_file::class, fn(iconitem_file $hook) => null);
        $icon->copy_to_day_filearea(500);
        $this->assertEmpty($fs->get_area_files($contextid, 'local_eventcalendar', 'dayicon', 500, 'id', false));

        $this->redirectHook(iconitem_file::class, function (iconitem_file $hook) use ($source): void {
            $hook->set_file($source);
        });
        $icon->copy_to_day_filearea(500);
        $this->assertTrue($fs->file_exists($contextid, 'local_eventcalendar', 'dayicon', 500, '/', 'star.svg'));
    }
}
