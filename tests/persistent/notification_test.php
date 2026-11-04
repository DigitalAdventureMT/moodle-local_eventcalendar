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

namespace local_eventcalendar\persistent;

use local_eventcalendar\local\calendar_generator;

#[\PHPUnit\Framework\Attributes\CoversClass(notification::class)]
/**
 * Tests of the text of the notifications as it is copied into the days.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\persistent\notification
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_test extends \advanced_testcase {
    /**
     * Creates a set with a title and a body with an image, saved with the placeholder of the editor.
     *
     * @return array [set, title, body]
     */
    protected function create_set(): array {
        $set = new notification_set(0, (object) ['name' => 'Set']);
        $set->create();

        $title = new notification(0, (object) [
            'setid' => $set->get('id'), 'kind' => 'title', 'defaultmessage' => 'Title @@PLUGINFILE@@',
            'defaultmessageformat' => FORMAT_PLAIN,
        ]);
        $title->create();

        $body = new notification(0, (object) [
            'setid' => $set->get('id'), 'kind' => 'body', 'defaultmessage' => '<p><img src="@@PLUGINFILE@@/pic.png" alt=""></p>',
            'defaultmessageformat' => FORMAT_HTML,
        ]);
        $body->create();

        return [$set, $title, $body];
    }

    /**
     * The images of a body point to the file of the notification; a title is returned as it is.
     */
    public function test_get_message_text(): void {
        $this->resetAfterTest();
        [, $title, $body] = $this->create_set();

        $expected = \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'local_eventcalendar',
            'notificationmessage',
            $body->get('id'),
            '/',
            'pic.png'
        )->out();

        $this->assertStringContainsString('src="' . $expected . '"', $body->get_message_text());
        $this->assertSame('Title @@PLUGINFILE@@', $title->get_message_text());
    }

    /**
     * The days generated from the set get the body with the address of the image, not the placeholder.
     */
    public function test_generated_days_get_resolved_body(): void {
        $this->resetAfterTest();
        [$set, , $body] = $this->create_set();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['notificationsetid' => $set->get('id')]);
        calendar_generator::sync_extend($calendar);

        $days = day::get_records(['calendarid' => $calendar->get('id')]);
        $this->assertNotEmpty($days);
        foreach ($days as $day) {
            $this->assertSame($body->get_message_text(), $day->get('notifbody'));
        }
    }
}
