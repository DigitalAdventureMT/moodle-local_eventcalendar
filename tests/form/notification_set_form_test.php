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

namespace local_eventcalendar\form;

use local_eventcalendar\hook\notificationset_form_definition;
use local_eventcalendar\hook\notificationset_form_validation;
use local_eventcalendar\local\content_pack_repository;
use local_eventcalendar\persistent\notification_set;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\notification_set_form::class)]
/**
 * Tests of the notification set form.
 *
 * The hooks are redirected to empty callbacks, so the callbacks of other plugins do not change the result.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\notification_set_form
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_set_form_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectHook(notificationset_form_definition::class, fn() => null);
        $this->redirectHook(notificationset_form_validation::class, fn() => null);
    }

    /**
     * Returns the key of a pack that can be imported, or skips the test when there is none.
     *
     * @return string
     */
    protected function get_importable_packkey(): string {
        $available = content_pack_repository::get_available_for_import();
        if (empty($available)) {
            $this->markTestSkipped('No content pack available for import.');
        }
        return array_key_first($available);
    }

    /**
     * Submits the form of a new set and returns it.
     *
     * @param array $values Submitted values.
     * @param string $packkey Pack loaded in the form.
     * @return notification_set_form
     */
    protected function submit(array $values, string $packkey = ''): notification_set_form {
        notification_set_form::mock_submit($values);
        return new notification_set_form(
            new \moodle_url('/local/eventcalendar/notifications.php'),
            ['persistent' => new notification_set(0), 'packkey' => $packkey]
        );
    }

    /**
     * A new set needs a pack.
     */
    public function test_new_set_requires_pack(): void {
        $form = $this->submit(['name' => 'Set', 'importpack' => '']);

        $this->assertFalse($form->is_from_pack());
        $this->assertEmpty($form->get_data());
    }

    /**
     * A pack chosen but not loaded is refused; the loaded pack is accepted.
     */
    public function test_pack_must_be_loaded(): void {
        $packkey = $this->get_importable_packkey();

        $notloaded = $this->submit(['name' => 'Set', 'importpack' => $packkey]);
        $this->assertEmpty($notloaded->get_data());

        $loaded = $this->submit(['name' => 'Set', 'importpack' => $packkey], $packkey);
        $this->assertTrue($loaded->is_from_pack());
        $data = $loaded->get_data();
        $this->assertNotNull($data);
        $this->assertSame($packkey, $data->importpack);
    }

    /**
     * An existing set has no pack select and needs only the name.
     */
    public function test_existing_set(): void {
        $set = new notification_set(0, (object) ['name' => 'Set']);
        $set->create();

        notification_set_form::mock_submit(['id' => $set->get('id'), 'name' => 'Renamed']);
        $form = new notification_set_form(
            new \moodle_url('/local/eventcalendar/notifications.php'),
            ['persistent' => $set]
        );

        $data = $form->get_data();
        $this->assertNotNull($data);
        $this->assertSame('Renamed', $data->name);
    }
}
