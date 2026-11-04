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

#[\PHPUnit\Framework\Attributes\CoversClass(daycontent_renderer::class)]
/**
 * Tests of the day content built from the template templates/daycontent/default.html.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\daycontent_renderer
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class daycontent_renderer_test extends \advanced_testcase {
    /**
     * Every placeholder of the template is replaced, and the content appears only once: the comment at the top
     * of the template names the placeholders without braces, so it is not replaced as well.
     */
    public function test_content_appears_once(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $day = $generator->create_day([
            'calendarid' => $calendar->get('id'),
            'content' => '<p>Unique day text</p>',
        ]);

        $html = daycontent_renderer::render($day, $calendar);

        $this->assertSame(1, substr_count($html, 'Unique day text'));
        $this->assertStringNotContainsString('{{', $html);
    }
}
