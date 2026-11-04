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
 * Persistent class for an icon set.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for an icon set.
 *
 * The icons of the set are rows of the iconitem table. "Standard" sets are
 * static files shipped in pix/iconsets/{identifier}/ (or in a graphics pack),
 * with one iconitem row created for each of them by the folder scan. "Custom"
 * sets are created and edited by other plugins, which keep their files.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iconset extends \core\persistent {
    /** Table of this persistent class. */
    const TABLE = 'local_eventcalendar_icons';

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
            'source' => [
                'type' => PARAM_ALPHA,
                'default' => 'custom',
            ],
            'identifier' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => '',
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
     * Validates the source field.
     *
     * @param string $value
     * @return true|\lang_string
     */
    protected function validate_source($value) {
        if (!in_array($value, ['standard', 'custom'], true)) {
            return new \lang_string('errorinvalidiconsetsource', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Tells whether the set is custom, that is managed by another plugin.
     *
     * "Standard" sets are shipped with the plugin and cannot be edited.
     *
     * @return bool
     */
    public function is_custom(): bool {
        return $this->get('source') === 'custom';
    }

    /**
     * Returns the icons of the set (iconitem rows), ordered by id.
     *
     * @return iconitem[]
     */
    public function get_items(): array {
        return iconitem::get_records(['iconsetid' => $this->get('id')], 'id', 'ASC');
    }

    /**
     * Returns the URLs of the icons of the set, whatever their source.
     *
     * @return \moodle_url[]
     */
    public function get_icon_urls(): array {
        $urls = [];

        foreach ($this->get_items() as $item) {
            $urls[] = $item->get_url();
        }

        return $urls;
    }
}
