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

use local_eventcalendar\hook\calendar_form_data;
use local_eventcalendar\hook\calendar_form_definition;
use local_eventcalendar\persistent\calendar;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\calendar_form::class)]
/**
 * Tests of the galleries of the calendar form.
 *
 * The hooks are redirected to empty callbacks, so the callbacks of other plugins do not change the result.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\calendar_form
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar_form_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectHook(calendar_form_definition::class, fn() => null);
        $this->redirectHook(calendar_form_data::class, fn() => null);
    }

    /**
     * Returns the HTML of a static element of the form, after definition_after_data().
     *
     * @param calendar_form $form
     * @param string $name
     * @return string
     */
    protected function get_static_html(calendar_form $form, string $name): string {
        $form->definition_after_data();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        return $mform->getElement($name)->toHtml();
    }

    /**
     * A new calendar starts with the first banner and no header and footer; the options of other plugins
     * follow the preloaded elements and are checked when they say so.
     */
    public function test_galleries(): void {
        global $DB;

        $bannerid = $DB->insert_record('local_eventcalendar_banner', (object) [
            'name' => 'Banner', 'identifier' => 'b' . random_string(5), 'filename' => 'b.png',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $form = new calendar_form(null, ['persistent' => new calendar(0)]);
        $form->add_gallery_option(calendar_form::GALLERY_BANNER, 'custom', 'Upload');
        $form->add_gallery_option(calendar_form::GALLERY_HEADERFOOTER, 'custom', 'Upload', true);

        $banner = $this->get_static_html($form, 'bannergallery');
        $this->assertMatchesRegularExpression('/value="' . $bannerid . '"[^>]*checked/', $banner);
        $this->assertStringContainsString('value="custom"', $banner);
        $this->assertMatchesRegularExpression('/value="custom"[^>]*checked/', $this->get_static_html($form, 'headerfootergallery'));
    }

    /**
     * A number or 'none' cannot be the value of an added option.
     */
    public function test_invalid_gallery_option(): void {
        $form = new calendar_form(null, ['persistent' => new calendar(0)]);

        $this->expectException(\coding_exception::class);
        $form->add_gallery_option(calendar_form::GALLERY_HEADERFOOTER, 'none', 'None');
    }
}
