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
 * Import of the elements shipped in the folders of the plugin and of the graphics packs.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Import of the elements shipped in the folders of the plugin and of the graphics packs.
 *
 * Runs the four scans (icon sets, email templates, banners, header/footer pairs) at the installation and at
 * every upgrade of the plugin, so that the elements added by a new version, or by a graphics pack installed
 * meanwhile, are available without pressing the detection buttons of the management pages.
 *
 * The scans only add the elements missing from the database: those already imported are never changed, also
 * when their files have changed. An element deleted from the database while its files are still in the
 * folders is imported again, as with the detection buttons.
 *
 * Each scan runs on its own: an error (for example a malformed metadata file) is reported with debugging() and
 * does not stop the other scans, nor the installation or the upgrade. The detection buttons stay available to
 * try again after fixing the files.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shipped_elements {
    /** Scanner classes, with the description used in the error messages. */
    private const SCANNERS = [
        iconset_scanner::class => 'standard icon sets',
        email_template_scanner::class => 'email templates',
        banner_scanner::class => 'preloaded banners',
        headerfooter_scanner::class => 'header/footer pairs',
    ];

    /**
     * Imports the shipped elements missing from the database.
     *
     * @return void
     */
    public static function import(): void {
        foreach (self::SCANNERS as $scanner => $description) {
            try {
                $scanner::scan();
            } catch (\Throwable $e) {
                debugging('Automatic scan of ' . $description . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
