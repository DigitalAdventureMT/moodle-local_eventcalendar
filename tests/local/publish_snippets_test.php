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

use local_eventcalendar\hook\publish_snippets as publish_snippets_hook;

#[\PHPUnit\Framework\Attributes\CoversClass(publish_snippets::class)]
/**
 * Tests of the ready-made code used to publish a calendar.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\publish_snippets
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publish_snippets_test extends \advanced_testcase {
    /**
     * Creates a banner record usable by calendars.
     *
     * @return int banner id
     */
    protected function create_banner(): int {
        global $DB;

        return $DB->insert_record('local_eventcalendar_banner', (object) [
            'name' => 'Banner', 'identifier' => 'testbanner', 'filename' => 'Banner01.png',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Returns the options of this plugin only: the callbacks of other plugins are replaced by an empty one.
     *
     * @param \local_eventcalendar\persistent\calendar $calendar
     * @return array
     */
    protected function own_snippets(\local_eventcalendar\persistent\calendar $calendar): array {
        $this->redirectHook(publish_snippets_hook::class, function (): void {
        });

        return publish_snippets::get_snippets($calendar);
    }

    /**
     * Returns the options indexed by key.
     *
     * @param array $snippets result of publish_snippets::get_snippets()
     * @return array
     */
    protected function by_key(array $snippets): array {
        return array_column($snippets, null, 'key');
    }

    /**
     * The plugin offers link, card and button; the HTML snippets link to the calendar page, escape the calendar
     * name and survive the HTML cleaning of Moodle.
     */
    public function test_html_snippets(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')
            ->create_calendar(['name' => 'Advent & "Christmas"']);
        $url = (new \moodle_url('/local/eventcalendar/view.php', ['id' => $calendar->get('id')]))->out(false);

        $snippets = $this->own_snippets($calendar);

        $this->assertSame(['link', 'card', 'button'], array_column($snippets, 'key'));

        // Whitespace and entity forms may change in the cleaning, the markup must not.
        $normalise = function (string $html): string {
            return preg_replace('/\s+/', '', html_entity_decode(str_replace(' />', '>', $html), ENT_QUOTES));
        };
        foreach (['link', 'card', 'button'] as $key) {
            $html = $this->by_key($snippets)[$key]['code'];
            $this->assertSame($html, $this->by_key($snippets)[$key]['preview']);
            $this->assertStringContainsString('href="' . s($url) . '"', $html);
            $this->assertStringContainsString('Advent &amp; &quot;Christmas&quot;', $html);
            $this->assertSame($normalise($html), $normalise(clean_text($html, FORMAT_HTML)));
        }

        $options = $this->by_key($snippets);
        $this->assertStringNotContainsString('<img', $options['card']['code']);
        $this->assertStringContainsString('stretched-link', $options['card']['code']);
        $this->assertStringContainsString('style="width: 33%; min-width: 200px; max-width: 550px;"', $options['card']['code']);
        $this->assertStringContainsString('btn btn-primary', $options['button']['code']);
        $this->assertNull($options['button']['image']);
        $this->assertNull($options['button']['link']);
    }

    /**
     * With a banner the card shows it as a decorative image; the list of options does not change.
     */
    public function test_card_with_banner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')
            ->create_calendar(['name' => 'Advent', 'bannerid' => $this->create_banner()]);
        $bannerurl = s($calendar->get_banner_url()->out(false));

        $snippets = $this->own_snippets($calendar);
        $options = $this->by_key($snippets);

        $this->assertSame(['link', 'card', 'button'], array_column($snippets, 'key'));
        $this->assertStringContainsString('<img src="' . $bannerurl . '" class="card-img-top" alt="">', $options['card']['code']);
    }
}
