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
 * Scan of pix/headerfooters/ to detect new header/footer pairs.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\headerfooter;

/**
 * Scan of pix/headerfooters/ to detect new header/footer pairs.
 *
 * The pix/headerfooters folder is looked for in the plugin and in the graphics packs (see graphics_locator).
 * In each folder a single metadata file describes the whole folder:
 *
 * {
 *   "pairs": [
 *     {"header": "Header1.png", "footer": "Footer1.png"},
 *     {"header": "Header2.png", "footer": "Footer2.png"}
 *   ]
 * }
 *
 * There is no "name" field: the internal identifier used to keep the scan idempotent is the header file
 * name without extension, never shown to the user. An identifier already imported (from the plugin or from an
 * earlier root) is never imported again, so duplicates found in later roots are ignored.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class headerfooter_scanner {
    /**
     * Runs the scan and imports into the database the pairs not yet present.
     *
     * @return \stdClass ->created (int, number of imported pairs), ->skipped (objects {identifier, reason})
     */
    public static function scan(): \stdClass {
        $result = new \stdClass();
        $result->created = 0;
        $result->skipped = [];

        foreach (graphics_locator::get_roots(graphics_locator::HEADERFOOTERS) as $root) {
            self::scan_root($root['dir'], $result);
        }

        return $result;
    }

    /**
     * Scans a single header/footer folder, adding the outcome to the result.
     *
     * @param string $basedir Absolute path of the folder.
     * @param \stdClass $result Result object shared by all the roots.
     */
    protected static function scan_root(string $basedir, \stdClass $result): void {
        $metadatafile = $basedir . '/headerfooters.json';

        if (!is_file($metadatafile)) {
            return;
        }

        $metadata = json_decode(file_get_contents($metadatafile));

        if ($metadata === null || empty($metadata->pairs) || !is_array($metadata->pairs)) {
            $result->skipped[] = (object) [
                'identifier' => 'headerfooters.json',
                'reason' => get_string('headfootscaninvalidmetadata', 'local_eventcalendar'),
            ];
            return;
        }

        foreach ($metadata->pairs as $pair) {
            if (empty($pair->header) || empty($pair->footer)) {
                $result->skipped[] = (object) [
                    'identifier' => $pair->header ?? '?',
                    'reason' => get_string('headfootscaninvalidmetadata', 'local_eventcalendar'),
                ];
                continue;
            }

            $headerfilename = $pair->header;
            $footerfilename = $pair->footer;
            $identifier = pathinfo($headerfilename, PATHINFO_FILENAME);

            if (!preg_match('/^[a-z0-9_-]+$/i', $identifier)) {
                $result->skipped[] = (object) [
                    'identifier' => $headerfilename,
                    'reason' => get_string('headfootscaninvalidname', 'local_eventcalendar'),
                ];
                continue;
            }

            if (!is_file($basedir . '/' . $headerfilename) || !is_file($basedir . '/' . $footerfilename)) {
                $result->skipped[] = (object) [
                    'identifier' => $headerfilename,
                    'reason' => get_string('headfootscanfilemissing', 'local_eventcalendar'),
                ];
                continue;
            }

            // Already imported: the scan does not touch it again.
            if (headerfooter::get_record(['identifier' => $identifier])) {
                continue;
            }

            $newpair = new headerfooter(0);
            $newpair->set('identifier', $identifier);
            $newpair->set('headerfilename', $headerfilename);
            $newpair->set('footerfilename', $footerfilename);
            $newpair->create();

            $result->created++;
        }
    }
}
