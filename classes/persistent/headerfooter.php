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
 * Persistent class for a header/footer pair shipped with the plugin.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

use local_eventcalendar\local\graphics_locator;

/**
 * Persistent class for a header/footer pair shipped with the plugin.
 *
 * The static files live in pix/headerfooters, in the plugin or in a graphics pack, and are imported by
 * the headerfooter scanner. The pairs are read-only in the interface and have no name, only a visual preview.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class headerfooter extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_headfoot';

    /**
     * Defines the persistent properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'identifier' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'headerfilename' => [
                'type' => PARAM_FILE,
            ],
            'footerfilename' => [
                'type' => PARAM_FILE,
            ],
            'usercreated' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Sets usercreated to the current user on creation only.
     */
    protected function before_create() {
        global $USER;

        if (empty($this->raw_get('usercreated'))) {
            $this->raw_set('usercreated', $USER->id);
        }
    }

    /**
     * Returns the URL of the static header file.
     *
     * @return \moodle_url
     */
    public function get_header_url(): \moodle_url {
        return graphics_locator::get_url(graphics_locator::HEADERFOOTERS, $this->get('headerfilename'));
    }

    /**
     * Returns the URL of the static footer file.
     *
     * @return \moodle_url
     */
    public function get_footer_url(): \moodle_url {
        return graphics_locator::get_url(graphics_locator::HEADERFOOTERS, $this->get('footerfilename'));
    }
}
