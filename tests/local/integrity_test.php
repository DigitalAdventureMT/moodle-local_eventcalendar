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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\integrity::class)]
/**
 * Tests for the referential integrity checks.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\integrity
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class integrity_test extends \advanced_testcase {
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
     * Inserts an icon set and returns its id.
     *
     * @param string $identifier
     * @return int
     */
    protected function create_iconset(string $identifier): int {
        global $DB;

        return (int) $DB->insert_record('local_eventcalendar_icons', (object) [
            'name' => $identifier, 'source' => 'custom', 'identifier' => $identifier,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Inserts a notification set and returns its id.
     *
     * @param string $name
     * @return int
     */
    protected function create_notificationset(string $name): int {
        global $DB;

        return (int) $DB->insert_record('local_eventcalendar_notifset', (object) [
            'name' => $name, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    public function test_calendar_names_using_iconset(): void {
        $used = $this->create_iconset('used');
        $unused = $this->create_iconset('unused');
        $second = $this->generator()->create_calendar(['name' => 'Beta', 'iconsetid' => $used]);
        $first = $this->generator()->create_calendar(['name' => 'Alfa', 'iconsetid' => $used]);
        $this->generator()->create_calendar(['name' => 'Gamma']);

        // Ordered by name, indexed by calendar id.
        $this->assertSame(
            [(int) $first->get('id') => 'Alfa', (int) $second->get('id') => 'Beta'],
            array_map('strval', integrity::get_calendar_names_using_iconset($used))
        );
        $this->assertSame([], integrity::get_calendar_names_using_iconset($unused));
    }

    public function test_release_iconset(): void {
        global $DB;

        $setid = $this->create_iconset('set');
        $using = $this->generator()->create_calendar(['iconsetid' => $setid]);
        $other = $this->generator()->create_calendar();

        integrity::release_iconset($setid);

        $this->assertEquals(0, $DB->get_field('local_eventcalendar_calendar', 'iconsetid', ['id' => $using->get('id')]));
        $this->assertEquals(
            $other->get('iconsetid'),
            $DB->get_field('local_eventcalendar_calendar', 'iconsetid', ['id' => $other->get('id')])
        );

        // A calendar without a set can still be updated by the services.
        $calendar = new \local_eventcalendar\persistent\calendar($using->get('id'));
        $calendar->set('name', 'Renamed');
        $calendar->update();
        $this->assertSame('Renamed', (new \local_eventcalendar\persistent\calendar($using->get('id')))->get('name'));
    }

    public function test_calendar_names_using_notificationset(): void {
        $used = $this->create_notificationset('used');
        $unused = $this->create_notificationset('unused');
        $second = $this->generator()->create_calendar(['name' => 'Beta', 'notificationsetid' => $used]);
        $first = $this->generator()->create_calendar(['name' => 'Alfa', 'notificationsetid' => $used]);
        $this->generator()->create_calendar(['name' => 'Gamma']);

        // Ordered by name, indexed by calendar id.
        $this->assertSame(
            [(int) $first->get('id') => 'Alfa', (int) $second->get('id') => 'Beta'],
            array_map('strval', integrity::get_calendar_names_using_notificationset($used))
        );
        $this->assertSame([], integrity::get_calendar_names_using_notificationset($unused));
    }

    public function test_release_notificationset(): void {
        global $DB;

        $setid = $this->create_notificationset('set');
        $using = $this->generator()->create_calendar(['notificationsetid' => $setid]);
        $otherset = $this->create_notificationset('other');
        $other = $this->generator()->create_calendar(['notificationsetid' => $otherset]);

        integrity::release_notificationset($setid);

        $this->assertNull($DB->get_field('local_eventcalendar_calendar', 'notificationsetid', ['id' => $using->get('id')]));
        $this->assertEquals(
            $otherset,
            $DB->get_field('local_eventcalendar_calendar', 'notificationsetid', ['id' => $other->get('id')])
        );
    }

    public function test_calendar_names_using_emailtemplate(): void {
        global $DB;

        $templateid = (int) $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => 'Template', 'language' => 'it', 'identifier' => 'tpl', 'content' => '<p>{{content}}</p>',
            'isdefault' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $using = $this->generator()->create_calendar(['name' => 'Alfa', 'emailtemplateid' => $templateid]);
        $this->generator()->create_calendar(['name' => 'Beta']);

        $this->assertSame(
            [(int) $using->get('id') => 'Alfa'],
            array_map('strval', integrity::get_calendar_names_using_emailtemplate($templateid))
        );
        $this->assertSame([], integrity::get_calendar_names_using_emailtemplate($templateid + 1));
    }

    public function test_release_emailtemplate(): void {
        global $DB;

        $templateid = (int) $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => 'Template', 'language' => 'it', 'identifier' => 'tpl', 'content' => '<p>{{content}}</p>',
            'isdefault' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $using = $this->generator()->create_calendar(['emailtemplateid' => $templateid]);
        $other = $this->generator()->create_calendar();

        integrity::release_emailtemplate($templateid);

        $this->assertNull($DB->get_field('local_eventcalendar_calendar', 'emailtemplateid', ['id' => $using->get('id')]));
        $this->assertNull($DB->get_field('local_eventcalendar_calendar', 'emailtemplateid', ['id' => $other->get('id')]));
    }
}
