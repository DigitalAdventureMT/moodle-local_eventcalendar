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

#[\PHPUnit\Framework\Attributes\CoversClass(email_renderer::class)]
/**
 * Tests of the notification email built from the email template.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\email_renderer
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_renderer_test extends \advanced_testcase {
    /**
     * The first name of the recipient is inserted as text: any markup in it is escaped.
     *
     * Moodle cleans the names entered from its forms, so the name is written directly in the database,
     * as an import or another plugin could do.
     */
    public function test_firstname_is_escaped(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['name' => 'Advent']);
        $day = $generator->create_day(['calendarid' => $calendar->get('id')]);

        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'firstname', 'Anna<script>alert(1)</script>', ['id' => $user->id]);
        $user = \core_user::get_user($user->id);

        $html = email_renderer::render($day, $calendar, $user);

        $this->assertNotNull($html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('Anna&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
