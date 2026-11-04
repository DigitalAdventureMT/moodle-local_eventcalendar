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
 * Scan of pix/iconsets/ to detect new standard icon sets.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\iconset;
use local_eventcalendar\persistent\iconitem;

/**
 * Scan of pix/iconsets/ to detect new standard icon sets.
 *
 * The pix/iconsets folder is looked for in the plugin and in the graphics packs (see graphics_locator).
 * Each subfolder is a potential standard set. The set identifier is always the folder name, and the
 * displayed name is read from the single "name" field of an iconset.json file in the folder.
 *
 * Only sets missing from the database are inserted; names of already imported sets are not updated.
 * An identifier already imported (from the plugin or from an earlier root) is never imported again, so
 * duplicates found in later roots are ignored.
 * For each set an iconitem row is also created for every image file found, so each icon can be
 * assigned to a specific day.
 *
 * Used by db/install.php (seeding at installation) and by the scan button in iconsets.php.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iconset_scanner {
    /**
     * Runs the scan and inserts into the database the standard sets not yet present.
     *
     * @return \stdClass ->created (string[] created names), ->skipped (objects {identifier, reason})
     */
    public static function scan(): \stdClass {
        $result = new \stdClass();
        $result->created = [];
        $result->skipped = [];

        foreach (graphics_locator::get_roots(graphics_locator::ICONSETS) as $root) {
            self::scan_root($root['dir'], $result);
        }

        return $result;
    }

    /**
     * Scans a single iconsets folder, adding the outcome to the result.
     *
     * @param string $basedir Absolute path of the folder.
     * @param \stdClass $result Result object shared by all the roots.
     */
    protected static function scan_root(string $basedir, \stdClass $result): void {
        foreach (scandir($basedir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $folderpath = $basedir . '/' . $entry;

            if (!is_dir($folderpath)) {
                continue;
            }

            // The identifier must match the folder name exactly, because it is used to build the file path
            // (graphics_locator and iconitem::get_url()); it is accepted only if already safe, never sanitised.
            if (!preg_match('/^[a-z0-9_-]+$/i', $entry)) {
                $result->skipped[] = (object) [
                    'identifier' => $entry,
                    'reason' => get_string('iconsetscaninvalidname', 'local_eventcalendar'),
                ];
                continue;
            }

            // Already present: the scan only inserts new sets.
            if (iconset::get_record(['source' => 'standard', 'identifier' => $entry])) {
                continue;
            }

            $metadatafile = $folderpath . '/iconset.json';

            if (!is_file($metadatafile)) {
                $result->skipped[] = (object) [
                    'identifier' => $entry,
                    'reason' => get_string('iconsetscanmissingmetadata', 'local_eventcalendar'),
                ];
                continue;
            }

            $metadata = json_decode(file_get_contents($metadatafile));

            if ($metadata === null || empty($metadata->name)) {
                $result->skipped[] = (object) [
                    'identifier' => $entry,
                    'reason' => get_string('iconsetscaninvalidmetadata', 'local_eventcalendar'),
                ];
                continue;
            }

            $newiconset = new iconset(0);
            $newiconset->set('name', $metadata->name);
            $newiconset->set('source', 'standard');
            $newiconset->set('identifier', $entry);
            $newiconset->create();

            // One iconitem row per image file, so each icon of a standard set can be assigned to a day.
            // The extensions are matched in code and not with GLOB_BRACE, which is not available on every platform.
            foreach (scandir($folderpath) as $imagename) {
                if (!is_file($folderpath . '/' . $imagename) || !preg_match('/\.(svg|png|jpe?g)$/i', $imagename)) {
                    continue;
                }

                $item = new iconitem(0);
                $item->set('iconsetid', $newiconset->get('id'));
                $item->set('filename', $imagename);
                $item->create();
            }

            $result->created[] = $metadata->name;
        }
    }
}
