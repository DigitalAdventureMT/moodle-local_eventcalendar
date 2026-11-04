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
 * Hook dispatched when the file of an icon of a custom icon set is needed.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\iconitem;

/**
 * Hook dispatched when the file of an icon of a custom icon set is needed.
 *
 * It is dispatched by iconitem::copy_to_day_filearea() only for the icons of custom sets, whose files are kept
 * by the plugin that manages those sets: the icons of the standard sets are files of the plugin. The callback
 * that owns the file gives it with set_file(); the plugin then copies it into the area of the day. When no
 * callback gives a file, the day has no icon. The callbacks do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to provide the file of the icons of custom icon sets.')]
#[\core\attribute\tags('iconset')]
final class iconitem_file {
    /** @var \stored_file|null File of the icon, null until a callback gives it. */
    private ?\stored_file $file = null;

    /**
     * Creates the hook.
     *
     * @param iconitem $iconitem The icon whose file is needed.
     */
    public function __construct(
        /** @var iconitem The icon whose file is needed. */
        private readonly iconitem $iconitem,
    ) {
    }

    /**
     * Returns the icon whose file is needed.
     *
     * @return iconitem
     */
    public function get_iconitem(): iconitem {
        return $this->iconitem;
    }

    /**
     * Returns the file of the icon, or null when no callback gave it.
     *
     * @return \stored_file|null
     */
    public function get_file(): ?\stored_file {
        return $this->file;
    }

    /**
     * Gives the file of the icon.
     *
     * @param \stored_file $file
     */
    public function set_file(\stored_file $file): void {
        $this->file = $file;
    }
}
