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

use local_eventcalendar\hook\day_form_data;
use local_eventcalendar\hook\day_form_definition;
use local_eventcalendar\hook\day_form_validation;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\day_form::class)]
/**
 * Tests of the day form: title and body from the pools of the notification set.
 *
 * The hooks are redirected to empty callbacks, so the callbacks of other plugins do not change the result.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\day_form
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_form_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectHook(day_form_definition::class, fn() => null);
        $this->redirectHook(day_form_data::class, fn() => null);
        $this->redirectHook(day_form_validation::class, fn() => null);
    }

    /**
     * Submits the form of a day with a title and a body and returns the errors.
     *
     * @param day $day
     * @param string $title
     * @param string $body
     * @return array
     */
    protected function submit(day $day, string $title, string $body): array {
        day_form::mock_submit([
            'id' => $day->get('id'), 'calendarid' => $day->get('calendarid'),
            'notiftitle' => $title, 'notifbodytext' => $body, 'isready' => 0, 'notes' => '',
        ]);
        $form = new day_form(null, [
            'persistent' => $day,
            'calendar' => new calendar($day->get('calendarid')),
            'titlepool' => ['Pack title'],
            'bodypool' => ["Riga 1\nRiga 2"],
            'contentpool' => [],
            'isrestday' => false,
        ]);
        return $form->validation((array) $form->get_submitted_data(), []);
    }

    /**
     * Texts of the pools and the saved texts are accepted, others are refused; line endings do not matter.
     */
    public function test_texts_from_pack(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $day = $generator->create_day([
            'calendarid' => $calendar->get('id'), 'daynumber' => 1, 'notiftitle' => 'Saved', 'notifbody' => 'Saved body',
        ]);

        $this->assertSame([], $this->submit($day, 'Pack title', "Riga 1\r\nRiga 2"));
        $this->assertSame([], $this->submit($day, 'Saved', 'Saved body'));

        $errors = $this->submit($day, 'Typed by hand', 'Body typed by hand');
        $this->assertArrayHasKey('notiftitle', $errors);
        $this->assertArrayHasKey('notifbodytext', $errors);
    }
}
