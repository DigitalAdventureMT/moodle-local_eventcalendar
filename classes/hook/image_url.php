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
 * Hook dispatched when the address of an image of a calendar is resolved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;

/**
 * Hook dispatched when the address of an image of a calendar is resolved.
 *
 * It is dispatched by the persistent calendar for the logo, always, and for banner, header and footer only
 * when the calendar has no preloaded element (a preloaded banner or header and footer pair always wins).
 * The initial address is the one found by the plugin, null if none. The area is one of logo, banner,
 * headerimage and footerimage. The callbacks replace the address with set_url(). They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to provide the logo, banner, header and footer images of an event calendar.')]
#[\core\attribute\tags('calendar')]
final class image_url {
    /**
     * Creates the hook.
     *
     * @param calendar $calendar The calendar.
     * @param string $area Image area: logo, banner, headerimage or footerimage.
     * @param \moodle_url|null $url Address found by the plugin, null if none.
     */
    public function __construct(
        /** @var calendar The calendar. */
        private readonly calendar $calendar,
        /** @var string Image area. */
        private readonly string $area,
        /** @var \moodle_url|null Address of the image. */
        private ?\moodle_url $url,
    ) {
    }

    /**
     * Returns the calendar.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the image area: logo, banner, headerimage or footerimage.
     *
     * @return string
     */
    public function get_area(): string {
        return $this->area;
    }

    /**
     * Returns the address of the image, null if none.
     *
     * @return \moodle_url|null
     */
    public function get_url(): ?\moodle_url {
        return $this->url;
    }

    /**
     * Sets the address of the image.
     *
     * @param \moodle_url|null $url The address, null for no image.
     */
    public function set_url(?\moodle_url $url): void {
        $this->url = $url;
    }
}
