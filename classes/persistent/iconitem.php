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
 * Persistent class for a single icon of an icon set.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

use local_eventcalendar\hook\iconitem_file;
use local_eventcalendar\hook\iconitem_url;
use local_eventcalendar\local\graphics_locator;

/**
 * Persistent class for a single icon of an icon set.
 *
 * Custom sets are managed by other plugins, which keep their files: the address and the file of their icons
 * are given by those plugins through the iconitem_url and iconitem_file hooks. Without them the address is
 * the one of a file area of this plugin, which serves nothing, and the days get no icon.
 * Standard sets use a static file in pix/iconsets/{identifier}/{filename}, in the plugin or in a graphics
 * pack: the location is resolved by the graphics_locator.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iconitem extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_iconitem';

    /**
     * Defines the properties of the persistent.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'iconsetid' => [
                'type' => PARAM_INT,
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
     * Validates the iconsetid field.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_iconsetid($value) {
        if (!iconset::record_exists($value)) {
            return new \lang_string('errorinvalidiconset', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Returns the image URL of this icon, for both custom and standard sets.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        $parentset = new iconset($this->get('iconsetid'));

        if ($parentset->is_custom()) {
            $context = \context_system::instance();

            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'local_eventcalendar',
                'iconitem',
                $this->get('id'),
                '/',
                $this->get('filename')
            );

            // Other plugins can serve the icons of custom sets from their own file areas.
            $hook = new iconitem_url($this, $url);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);

            return $hook->get_url();
        }

        return graphics_locator::get_url(
            graphics_locator::ICONSETS,
            $parentset->get('identifier') . '/' . $this->get('filename')
        );
    }

    /**
     * Copies the icon image into the 'dayicon' file area of a day.
     *
     * The copy detaches the day icon from its set, so it survives if the set is deleted.
     * Custom sets are copied from the file given by the iconitem_file hook; standard sets from the static file
     * on disk.
     * @param int $dayid
     * @return void
     */
    public function copy_to_day_filearea(int $dayid): void {
        $context = \context_system::instance();
        $fs = get_file_storage();

        // Clear the area first: create_file_from_*() does not overwrite, so repeated calls
        // would otherwise leave multiple files for the same day.
        $fs->delete_area_files($context->id, 'local_eventcalendar', 'dayicon', $dayid);

        $targetrecord = [
            'contextid' => $context->id,
            'component' => 'local_eventcalendar',
            'filearea' => 'dayicon',
            'itemid' => $dayid,
            'filepath' => '/',
            'filename' => $this->get('filename'),
        ];

        $parentset = new iconset($this->get('iconsetid'));

        if ($parentset->is_custom()) {
            // The file is kept by the plugin that manages the custom sets.
            $hook = new iconitem_file($this);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);

            if ($hook->get_file()) {
                $fs->create_file_from_storedfile($targetrecord, $hook->get_file());
            }

            return;
        }

        // Standard set: the static file is searched in the plugin and in the graphics packs.
        $sourcepath = graphics_locator::find_file(
            graphics_locator::ICONSETS,
            $parentset->get('identifier') . '/' . $this->get('filename')
        );

        if ($sourcepath !== null) {
            $fs->create_file_from_pathname($targetrecord, $sourcepath);
        }
    }
}
