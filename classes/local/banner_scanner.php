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
 * Scanner for the banners shipped in pix/banners.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\banner;

/**
 * Scanner for the banners shipped in pix/banners.
 *
 * The pix/banners folder is looked for in the plugin and in the graphics packs (see graphics_locator).
 * In each folder the metadata of all banners is kept in a single file, banners.json,
 * as a list of {filename, name}:
 *
 * {
 *   "banners": [
 *     {"filename": "Banner1.png", "name": "Banner 1"},
 *     {"filename": "Banner2.png", "name": "Banner 2"}
 *   ]
 * }
 *
 * New banners are imported immediately; existing records are never modified by the scan.
 * An identifier already imported (from the plugin or from an earlier root) is never imported again,
 * so duplicates found in later roots are ignored.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner_scanner {
    /**
     * Scans the banner folders and imports the banners not yet in the database.
     *
     * @return \stdClass ->created (string[] imported names), ->skipped (objects {identifier, reason}).
     */
    public static function scan(): \stdClass {
        $result = new \stdClass();
        $result->created = [];
        $result->skipped = [];

        foreach (graphics_locator::get_roots(graphics_locator::BANNERS) as $root) {
            self::scan_root($root['dir'], $result);
        }

        return $result;
    }

    /**
     * Scans a single banner folder, adding the outcome to the result.
     *
     * @param string $basedir Absolute path of the folder.
     * @param \stdClass $result Result object shared by all the roots.
     */
    protected static function scan_root(string $basedir, \stdClass $result): void {
        $metadatafile = $basedir . '/banners.json';

        if (!is_file($metadatafile)) {
            // No metadata file means no banners to import, which is not an error.
            return;
        }

        $metadata = json_decode(file_get_contents($metadatafile));

        if ($metadata === null || empty($metadata->banners) || !is_array($metadata->banners)) {
            $result->skipped[] = (object) [
                'identifier' => 'banners.json',
                'reason' => get_string('bannerscaninvalidmetadata', 'local_eventcalendar'),
            ];
            return;
        }

        foreach ($metadata->banners as $entry) {
            if (empty($entry->filename) || empty($entry->name)) {
                $result->skipped[] = (object) [
                    'identifier' => $entry->filename ?? '?',
                    'reason' => get_string('bannerscaninvalidmetadata', 'local_eventcalendar'),
                ];
                continue;
            }

            $filename = $entry->filename;
            $identifier = pathinfo($filename, PATHINFO_FILENAME);

            if (!preg_match('/^[a-z0-9_-]+$/i', $identifier)) {
                $result->skipped[] = (object) [
                    'identifier' => $filename,
                    'reason' => get_string('bannerscaninvalidname', 'local_eventcalendar'),
                ];
                continue;
            }

            if (!is_file($basedir . '/' . $filename)) {
                $result->skipped[] = (object) [
                    'identifier' => $filename,
                    'reason' => get_string('bannerscanfilemissing', 'local_eventcalendar'),
                ];
                continue;
            }

            // Already imported: leave it untouched.
            if (banner::get_record(['identifier' => $identifier])) {
                continue;
            }

            $newbanner = new banner(0);
            $newbanner->set('name', $entry->name);
            $newbanner->set('identifier', $identifier);
            $newbanner->set('filename', $filename);
            $newbanner->create();

            $result->created[] = $entry->name;
        }
    }
}
