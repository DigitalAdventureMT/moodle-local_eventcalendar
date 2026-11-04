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
 * Hook dispatched when the address of an icon of a custom icon set is resolved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\iconitem;

/**
 * Hook dispatched when the address of an icon of a custom icon set is resolved.
 *
 * It is dispatched by the persistent iconitem only for the icons of custom sets; the icons of the standard
 * sets are files of the plugin and their address does not change. The initial address is the one built by
 * the plugin. The callbacks replace it with set_url(). They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to provide the address of the icons of custom icon sets.')]
#[\core\attribute\tags('iconset')]
final class iconitem_url {
    /**
     * Creates the hook.
     *
     * @param iconitem $iconitem The icon.
     * @param \moodle_url $url Address built by the plugin.
     */
    public function __construct(
        /** @var iconitem The icon. */
        private readonly iconitem $iconitem,
        /** @var \moodle_url Address of the icon. */
        private \moodle_url $url,
    ) {
    }

    /**
     * Returns the icon.
     *
     * @return iconitem
     */
    public function get_iconitem(): iconitem {
        return $this->iconitem;
    }

    /**
     * Returns the address of the icon.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return $this->url;
    }

    /**
     * Sets the address of the icon.
     *
     * @param \moodle_url $url The address.
     */
    public function set_url(\moodle_url $url): void {
        $this->url = $url;
    }
}
