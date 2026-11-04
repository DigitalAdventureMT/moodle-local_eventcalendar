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
 * Bootstrap class names that differ between Moodle versions.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Bootstrap class names that differ between Moodle versions.
 *
 * Moodle 4.x uses Bootstrap 4, Moodle 5.0 and later Bootstrap 5. Bootstrap 4 classes still work in
 * Moodle 5.x through a deprecated compatibility layer, which is flagged in theme designer mode and
 * will be removed in Moodle 6.0, so each version gets its own class names. Spacing and font weight
 * use the plugin classes in styles.css instead, which are the same in every version.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bootstrap {
    /**
     * Whether the site runs Bootstrap 5 (Moodle 5.0 or later).
     *
     * @return bool
     */
    public static function is_bs5(): bool {
        global $CFG;

        return (int) $CFG->branch >= 500;
    }

    /**
     * Classes of a badge with a theme colour and a readable text colour.
     *
     * @param string $variant colour name, for example success, danger, secondary
     * @return string
     */
    public static function badge(string $variant): string {
        return self::is_bs5() ? 'badge text-bg-' . $variant : 'badge badge-' . $variant;
    }

    /**
     * Class of a styled select element.
     *
     * @return string
     */
    public static function select(): string {
        return self::is_bs5() ? 'form-select' : 'custom-select';
    }
}
