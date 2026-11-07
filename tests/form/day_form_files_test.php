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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\day_form::class)]
/**
 * Tests of the options of the day content editor. The server-side type check is tested in
 * \local_eventcalendar\local\draft_files_test.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\day_form
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_form_files_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The options enforced on the server match those declared to the browser.
     */
    public function test_editor_options(): void {
        $options = day_form::editor_options();

        $this->assertSame(['web_image'], $options['accepted_types']);
        $this->assertFalse($options['subdirs']);
        $this->assertGreaterThan(0, $options['maxbytes']);
        $this->assertSame(EDITOR_UNLIMITED_FILES, $options['maxfiles']);
    }
}
