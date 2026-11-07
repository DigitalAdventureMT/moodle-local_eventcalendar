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

/**
 * Hook dispatched when the call to action of a day content is built.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Hook dispatched when the call to action of a day content is built.
 *
 * It is dispatched by local\daycontent_renderer for the {{ctabutton}} placeholder. The plugin has no call to
 * action, so the initial HTML is empty. It is not dispatched on weekly rest days, which never have a call to
 * action. In the live preview of the day form, get_overrides() returns the raw values of the submitted form,
 * not yet saved and not cleaned: the callbacks read the fields they added and clean them with clean_param().
 * Otherwise it is empty. The callbacks set the HTML with set_html(). They do not return a value.
 *
 * Trusted HTML: the button is printed as it is in the day page, seen by every user of the calendar, and in the
 * notification emails. The callbacks must escape every value they did not write themselves, above all the
 * texts and the addresses entered by the managers and the raw values of get_overrides(): s() or
 * format_string() for text, html_writer for tags and attributes, and a \moodle_url (or clean_param() with
 * PARAM_URL) for the addresses, so that a javascript: address cannot be used.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to build the call to action button of an event calendar day.')]
#[\core\attribute\tags('day')]
final class day_cta {
    /**
     * Creates the hook.
     *
     * @param day $day The day whose content is built.
     * @param calendar $calendar The calendar of the day.
     * @param array $overrides Values submitted by the preview of the day form, not yet saved.
     * @param string $html HTML of the button built by the plugin, empty if none.
     */
    public function __construct(
        /** @var day The day whose content is built. */
        private readonly day $day,
        /** @var calendar The calendar of the day. */
        private readonly calendar $calendar,
        /** @var array Values submitted by the preview of the day form. */
        private readonly array $overrides,
        /** @var string HTML of the button. */
        private string $html,
    ) {
    }

    /**
     * Returns the day whose content is built.
     *
     * @return day
     */
    public function get_day(): day {
        return $this->day;
    }

    /**
     * Returns the calendar of the day.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the values submitted by the preview of the day form, empty outside the preview.
     *
     * @return array
     */
    public function get_overrides(): array {
        return $this->overrides;
    }

    /**
     * Returns the HTML of the button, empty if none.
     *
     * @return string
     */
    public function get_html(): string {
        return $this->html;
    }

    /**
     * Sets the HTML of the button.
     *
     * @param string $html HTML printed as it is (see the class description), empty for no button.
     */
    public function set_html(string $html): void {
        $this->html = $html;
    }
}
