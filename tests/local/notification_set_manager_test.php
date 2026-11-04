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

use local_eventcalendar\hook\notificationset_saved;
use local_eventcalendar\persistent\notification;
use local_eventcalendar\persistent\notification_set;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\notification_set_manager::class)]
/**
 * Tests for the service that manages notification sets.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\notification_set_manager
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_set_manager_test extends \advanced_testcase {
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
     * Creates a set with a title and a body, the latter with a file.
     *
     * @return array [set, title, body]
     */
    protected function create_set(): array {
        $set = new notification_set(0, (object) ['name' => 'Set']);
        $set->create();

        $title = new notification(0, (object) [
            'setid' => $set->get('id'), 'kind' => 'title', 'defaultmessage' => 'Title', 'defaultmessageformat' => FORMAT_PLAIN,
        ]);
        $title->create();

        $body = new notification(0, (object) [
            'setid' => $set->get('id'), 'kind' => 'body', 'defaultmessage' => '<p>Body</p>', 'defaultmessageformat' => FORMAT_HTML,
        ]);
        $body->create();

        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_eventcalendar',
            'filearea' => 'notificationmessage',
            'itemid' => $body->get('id'),
            'filepath' => '/',
            'filename' => 'image.png',
        ], 'content');

        return [$set, $title, $body];
    }

    /**
     * The set, its notifications and their files are deleted.
     */
    public function test_delete_removes_set_notifications_and_files(): void {
        [$set, $title, $body] = $this->create_set();

        notification_set_manager::delete($set);

        $this->assertFalse(notification_set::record_exists($set->get('id')));
        $this->assertFalse(notification::record_exists($title->get('id')));
        $this->assertFalse(notification::record_exists($body->get('id')));
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_eventcalendar',
            'notificationmessage',
            $body->get('id'),
            'id',
            false
        );
        $this->assertEmpty($files);
    }

    /**
     * A set assigned to a calendar can be deleted: the calendar loses the reference, the others keep theirs.
     */
    public function test_delete_set_in_use_releases_calendars(): void {
        global $DB;

        [$set] = $this->create_set();
        [$otherset] = $this->create_set();
        $using = $this->generator()->create_calendar(['notificationsetid' => $set->get('id')]);
        $other = $this->generator()->create_calendar(['notificationsetid' => $otherset->get('id')]);

        notification_set_manager::delete($set);

        $this->assertFalse(notification_set::record_exists($set->get('id')));
        $this->assertNull($DB->get_field('local_eventcalendar_calendar', 'notificationsetid', ['id' => $using->get('id')]));
        $this->assertEquals(
            $otherset->get('id'),
            $DB->get_field('local_eventcalendar_calendar', 'notificationsetid', ['id' => $other->get('id')])
        );
        $this->assertTrue(notification_set::record_exists($otherset->get('id')));
    }

    /**
     * Returns the key and the data of a pack that can be imported, or skips the test when there is none.
     *
     * @return array [packkey, pack]
     */
    protected function get_importable_pack(): array {
        $available = content_pack_repository::get_available_for_import();
        if (empty($available)) {
            $this->markTestSkipped('No content pack available for import.');
        }
        $packkey = array_key_first($available);
        return [$packkey, $available[$packkey]];
    }

    /**
     * A new set with a pack records the pack and gets its titles and bodies, read from disk and not from the
     * submitted data; the hook says the set is new.
     */
    public function test_save_creates_set_from_pack(): void {
        [$packkey, $pack] = $this->get_importable_pack();

        $seen = [];
        $this->redirectHook(notificationset_saved::class, function (notificationset_saved $hook) use (&$seen): void {
            $seen[] = ['name' => $hook->get_set()->get('name'), 'new' => $hook->is_new()];
        });

        $set = new notification_set(0);
        notification_set_manager::save($set, (object) [
            'name' => 'New set',
            'description' => 'Description',
            'importpack' => $packkey,
            'titletext' => ['Not from the pack'],
        ]);

        $saved = new notification_set($set->get('id'));
        $this->assertSame('New set', $saved->get('name'));
        $this->assertSame('Description', $saved->get('description'));
        $this->assertSame($packkey, $saved->get('sourcepack'));
        $this->assertEquals($pack->version, $saved->get('sourcepackversion'));
        $this->assertTrue($saved->is_locked());
        $this->assertSame(
            array_values($pack->subjects),
            array_values(array_map(fn($title) => $title->get('defaultmessage'), $saved->get_titles()))
        );
        $this->assertSame(
            array_values($pack->notifications),
            array_values(array_map(fn($body) => $body->get('defaultmessage'), $saved->get_bodies()))
        );
        $this->assertSame([['name' => 'New set', 'new' => true]], $seen);
    }

    /**
     * A new set without a pack is created empty and not locked: only other plugins create such sets.
     */
    public function test_save_creates_set_without_pack(): void {
        $set = new notification_set(0);
        notification_set_manager::save($set, (object) ['name' => 'Empty']);

        $saved = new notification_set($set->get('id'));
        $this->assertFalse($saved->is_locked());
        $this->assertEmpty($saved->get_notifications());
    }

    /**
     * Saving an existing set changes name and description only; the notifications stay, and the hook says the
     * set is not new.
     */
    public function test_save_updates_name_only(): void {
        [$set, $title, $body] = $this->create_set();

        $isnew = null;
        $this->redirectHook(notificationset_saved::class, function (notificationset_saved $hook) use (&$isnew): void {
            $isnew = $hook->is_new();
        });

        notification_set_manager::save($set, (object) [
            'name' => 'Renamed',
            'description' => 'New description',
            'titletext' => [''],
            'titleid' => [$title->get('id')],
        ]);

        $saved = new notification_set($set->get('id'));
        $this->assertFalse($isnew);
        $this->assertSame('Renamed', $saved->get('name'));
        $this->assertSame('New description', $saved->get('description'));
        $this->assertTrue(notification::record_exists($title->get('id')));
        $this->assertTrue(notification::record_exists($body->get('id')));
    }
}
