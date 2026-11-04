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
 * Persistent class for a banner shipped with the plugin.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

use local_eventcalendar\local\graphics_locator;

/**
 * Persistent class for a banner shipped with the plugin.
 *
 * A static file in pix/banners/{identifier}.{ext}, in the plugin or in a graphics pack, imported when
 * detected (classes/local/banner_scanner.php). It is offered in the calendar form
 * gallery as an alternative to manual upload (email template placeholder
 * {{banner}}). Read-only from the interface.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_banner';

    /**
     * Defines the persistent properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'identifier' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'filename' => [
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
     * Returns the URL of the static file in pix/banners/.
     *
     * The location (plugin or graphics pack) is resolved by the graphics_locator.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return graphics_locator::get_url(graphics_locator::BANNERS, $this->get('filename'));
    }
}
