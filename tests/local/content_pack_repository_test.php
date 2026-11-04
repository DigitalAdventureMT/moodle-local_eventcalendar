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

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\content_pack_repository::class)]
/**
 * Tests of the language of the calendar and of the content pack it selects.
 *
 * The tests do not depend on the content of the packs shipped with the plugin: they pick one of the
 * available packs and check that the calendar uses it, whatever the language of the user is.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\content_pack_repository
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_pack_repository_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Returns the pack used by the tests, preferring a language other than the one of the test
     * environment (English), so that the independence from the language of the user is really checked.
     *
     * @return \stdClass
     */
    protected function pick_pack(): \stdClass {
        $packs = content_pack_repository::get_all();
        $this->assertNotEmpty($packs, 'The plugin must ship at least one content pack.');

        foreach ($packs as $pack) {
            if ($pack->language !== current_language()) {
                return $pack;
            }
        }

        return reset($packs);
    }

    /**
     * Creates a calendar of three days with the given template and language.
     *
     * @param string|null $templatekey
     * @param string|null $language
     * @return calendar
     */
    protected function create_calendar(?string $templatekey, ?string $language): calendar {
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();

        $startdate = gmmktime(0, 0, 0, 12, 1, 2026);
        $calendar->set('templatekey', $templatekey);
        $calendar->set('language', $language);
        $calendar->set('tagline', '');
        $calendar->set('startdate', $startdate);
        $calendar->set('enddate', $startdate + (2 * DAYSECS));
        $calendar->update();

        return $calendar;
    }

    /**
     * The languages offered are exactly those of the available packs, each with a name to display.
     */
    public function test_get_languages(): void {
        $expected = [];
        foreach (content_pack_repository::get_all() as $pack) {
            $expected[$pack->language] = true;
        }
        ksort($expected);

        $languages = content_pack_repository::get_languages();

        $this->assertSame(array_keys($expected), array_keys($languages));
        foreach ($languages as $code => $name) {
            $this->assertNotSame('', $name, $code);
        }
    }

    /**
     * The pack of a calendar depends on its template and on its language, never on the language of the user.
     */
    public function test_get_for_calendar(): void {
        $pack = $this->pick_pack();

        $calendar = $this->create_calendar($pack->template_key, $pack->language);
        $found = content_pack_repository::get_for_calendar($calendar);
        $this->assertNotNull($found);
        $this->assertSame($pack->packkey, $found->packkey);

        // Without language, without template, or with a language lacking a pack for the template: no pack.
        $this->assertNull(content_pack_repository::get_for_calendar($this->create_calendar($pack->template_key, null)));
        $this->assertNull(content_pack_repository::get_for_calendar($this->create_calendar(null, $pack->language)));

        // A language without packs cannot be saved (the persistent validates it), so this case uses a calendar
        // that is only changed in memory.
        $unsaved = $this->create_calendar($pack->template_key, $pack->language);
        $unsaved->set('language', 'zz');
        $this->assertNull(content_pack_repository::get_for_calendar($unsaved));
    }

    /**
     * Only the languages of the available packs, or no language at all, are valid for a calendar.
     */
    public function test_language_validation(): void {
        $pack = $this->pick_pack();
        $calendar = $this->create_calendar($pack->template_key, $pack->language);

        $this->assertTrue($calendar->validate());

        $calendar->set('language', null);
        $this->assertTrue($calendar->validate());

        $calendar->set('language', 'zz');
        $errors = $calendar->validate();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('language', $errors);
    }

    /**
     * The generation takes tagline and day contents from the pack of the calendar language.
     */
    public function test_generation_uses_the_calendar_language(): void {
        $pack = $this->pick_pack();
        $calendar = $this->create_calendar($pack->template_key, $pack->language);

        $this->assertSame(3, calendar_generator::sync_extend($calendar));

        $calendar = new calendar($calendar->get('id'));
        $this->assertSame((string) $pack->tagline, (string) $calendar->get('tagline'));

        $allcontents = array_merge(
            $pack->daycontent_first,
            $pack->daycontent_last,
            $pack->daycontents,
            $pack->restday_daycontents
        );
        foreach (day::get_records(['calendarid' => $calendar->get('id')], 'daynumber') as $day) {
            $this->assertContains((string) $day->get('content'), array_map('strval', $allcontents));
        }
    }

    /**
     * Without a language the generation leaves the texts of the pack empty.
     */
    public function test_generation_without_language_uses_no_pack(): void {
        $pack = $this->pick_pack();
        $calendar = $this->create_calendar($pack->template_key, null);

        $this->assertSame(3, calendar_generator::sync_extend($calendar));

        $calendar = new calendar($calendar->get('id'));
        $this->assertSame('', (string) $calendar->get('tagline'));
        foreach (day::get_records(['calendarid' => $calendar->get('id')], 'daynumber') as $day) {
            $this->assertSame('', (string) $day->get('content'));
        }
    }

    /**
     * Every template has a complete pack in every language offered by the plugin.
     *
     * Unlike the other tests, this one checks the packs shipped with the plugin: a calendar created from a
     * template must find day contents, notification titles and bodies and a tagline in each language, otherwise
     * its days would be generated empty. The pools used for the days of the calendar (subjects, notifications,
     * daycontents) must not be empty; the first and last day contents are optional by design, but every shipped
     * pack provides them.
     */
    public function test_shipped_packs_are_complete(): void {
        $packs = content_pack_repository::get_all();
        $languages = array_keys(content_pack_repository::get_languages());

        foreach (array_keys(template_repository::get_all()) as $templatekey) {
            foreach ($languages as $language) {
                $packkey = $language . ':' . $templatekey;
                $this->assertArrayHasKey($packkey, $packs, "Missing content pack {$packkey}.");

                $pack = $packs[$packkey];
                $this->assertNotSame('', trim((string) $pack->tagline), "Empty tagline in {$packkey}.");
                foreach (['subjects', 'notifications', 'daycontents', 'daycontent_first', 'daycontent_last'] as $pool) {
                    $this->assertNotEmpty($pack->$pool, "Empty pool {$pool} in {$packkey}.");
                }
            }
        }
    }

    /**
     * The name of a pack shown to the user uses the name of the template and the name of the language, and falls
     * back to the technical values when the template or the language are unknown.
     */
    public function test_get_label(): void {
        $pack = (object) ['template_key' => 'advent', 'language' => 'en'];
        $this->assertSame(
            get_string('template_advent', 'local_eventcalendar') . ' (' . get_string_manager()->get_list_of_languages()['en'] . ')',
            content_pack_repository::get_label($pack)
        );

        $unknown = (object) ['template_key' => 'unknowntemplate', 'language' => 'zz'];
        $this->assertSame('unknowntemplate (ZZ)', content_pack_repository::get_label($unknown));
    }
}
