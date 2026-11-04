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

#[\PHPUnit\Framework\Attributes\CoversClass(unsubscribe::class)]
/**
 * Tests of the unsubscribe links of the notification emails.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\unsubscribe
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unsubscribe_test extends \advanced_testcase {
    /**
     * The token of a link is valid only for its calendar and user, and depends on the site secret.
     */
    public function test_token(): void {
        $this->resetAfterTest();

        $url = unsubscribe::get_url(3, 7);
        $token = $url->get_param('token');

        $this->assertEquals(3, $url->get_param('calendar'));
        $this->assertEquals(7, $url->get_param('user'));
        $this->assertNotEmpty(get_config('local_eventcalendar', 'unsubscribesecret'));
        $this->assertTrue(unsubscribe::is_valid_token(3, 7, $token));
        $this->assertFalse(unsubscribe::is_valid_token(3, 8, $token));
        $this->assertFalse(unsubscribe::is_valid_token(4, 7, $token));
        $this->assertFalse(unsubscribe::is_valid_token(3, 7, ''));

        // A new secret invalidates the links already sent.
        set_config('unsubscribesecret', 'other', 'local_eventcalendar');
        $this->assertFalse(unsubscribe::is_valid_token(3, 7, $token));
    }

    /**
     * The email template placeholder is replaced by the link of the recipient.
     */
    public function test_email_placeholder(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['name' => 'Advent']);
        $day = $generator->create_day(['calendarid' => $calendar->get('id'), 'opendate' => time()]);
        $user = $this->getDataGenerator()->create_user();

        $html = email_renderer::render($day, $calendar, $user);

        $this->assertNotNull($html);
        $this->assertStringNotContainsString('{{unsubscribeurl}}', $html);
        $this->assertStringContainsString(unsubscribe::get_url((int) $calendar->get('id'), (int) $user->id)->out(false), $html);
    }
}
