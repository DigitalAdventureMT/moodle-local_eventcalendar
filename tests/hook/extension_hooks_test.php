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

use local_eventcalendar\local\accessrule\registry;
use local_eventcalendar\local\accessrule\role;
use local_eventcalendar\local\publish_snippets as publish_snippets_builder;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\publish_snippets::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\access_rule_types::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\report_links::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\registry::class)]
/**
 * Tests of the hooks for publishing options, access rule types and report links.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\hook\publish_snippets
 * @covers      \local_eventcalendar\hook\access_rule_types
 * @covers      \local_eventcalendar\hook\report_links
 * @covers      \local_eventcalendar\local\accessrule\registry
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extension_hooks_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        registry::reset();
    }

    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * An option added by a callback comes after those of the plugin, with the optional keys filled in.
     */
    public function test_publish_snippets_adds_option(): void {
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();

        $this->redirectHook(publish_snippets::class, function (publish_snippets $hook): void {
            $hook->add_snippet([
                'key' => 'extra', 'title' => 'Extra', 'description' => 'Extra option', 'preview' => '<b>x</b>',
            ]);
        });

        $snippets = publish_snippets_builder::get_snippets($calendar);
        $last = end($snippets);

        $this->assertSame('link', $snippets[0]['key']);
        $this->assertSame('extra', $last['key']);
        $this->assertNull($last['code']);
        $this->assertNull($last['link']);
    }

    /**
     * An option without the required keys, or with a key already used, is refused.
     */
    public function test_publish_snippets_rejects_invalid_option(): void {
        $calendar = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar')->create_calendar();
        $hook = new publish_snippets($calendar, [['key' => 'link']]);

        try {
            $hook->add_snippet(['key' => 'nopreview', 'title' => 'T', 'description' => 'D']);
            $this->fail('Option without preview accepted');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('preview', $e->getMessage());
        }

        $this->expectException(\coding_exception::class);
        $hook->add_snippet(['key' => 'link', 'title' => 'T', 'description' => 'D', 'preview' => '']);
    }

    /**
     * A type added by a callback is returned by the registry after those of the plugin.
     */
    public function test_access_rule_types_extend_registry(): void {
        $this->redirectHook(access_rule_types::class, function (access_rule_types $hook): void {
            $hook->add_type('extratype', role::class);
        });

        $this->assertInstanceOf(role::class, registry::get('extratype'));
        $types = array_keys(registry::get_all());
        $this->assertSame('role', $types[0]);
        $this->assertSame('extratype', end($types));
        $this->assertNull(registry::get('missingtype'));
    }

    /**
     * Identifiers already used, invalid identifiers and classes that are not types are refused.
     */
    public function test_access_rule_types_rejects_invalid_types(): void {
        $hook = new access_rule_types(['role' => role::class]);

        foreach ([['role', role::class], ['Bad-Id', role::class], ['other', \stdClass::class]] as [$type, $class]) {
            try {
                $hook->add_type($type, $class);
                $this->fail('Type accepted: ' . $type);
            } catch (\coding_exception $e) {
                $this->assertSame([], $hook->get_types());
            }
        }
    }

    /**
     * The link to the report can be replaced or removed.
     */
    public function test_report_links(): void {
        $default = new \moodle_url('/local/eventcalendar/report.php', ['id' => 3]);
        $hook = new report_links(3, $default);

        $this->assertSame(3, $hook->get_calendarid());
        $this->assertSame($default, $hook->get_calendar_url());

        $other = new \moodle_url('/other.php');
        $hook->set_calendar_url($other);
        $this->assertSame($other, $hook->get_calendar_url());

        $hook->set_calendar_url(null);
        $this->assertNull($hook->get_calendar_url());
    }
}
