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
 * Scans email_templates/ to detect new email templates.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\email_template;

/**
 * Scans email_templates/ to detect new email templates.
 *
 * On disk: email_templates/{language}/{identifier}.html with a JSON metadata file of the same name
 * holding a "name" field. Templates are imported as soon as they are detected. Once imported, the
 * database content is never overwritten by the scan, even if the source file changes, and it stays
 * editable from the UI.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_template_scanner {
    /**
     * Scans the folder and imports the templates not yet in the database.
     *
     * @return \stdClass ->created (string[] imported names), ->skipped (objects {identifier, reason})
     */
    public static function scan(): \stdClass {
        global $CFG, $DB;

        $result = new \stdClass();
        $result->created = [];
        $result->skipped = [];

        $basedir = $CFG->dirroot . '/local/eventcalendar/email_templates';

        if (!is_dir($basedir)) {
            return $result;
        }

        foreach (scandir($basedir) as $langentry) {
            if ($langentry === '.' || $langentry === '..') {
                continue;
            }

            $langdir = $basedir . '/' . $langentry;

            if (!is_dir($langdir)) {
                continue;
            }

            // The language must be a safe code because it builds a path. It is not sanitized, to stay aligned
            // with the real filesystem.
            if (!preg_match('/^[a-z0-9_-]+$/i', $langentry)) {
                $result->skipped[] = (object) [
                    'identifier' => $langentry,
                    'reason' => get_string('emailtplscaninvalidlanguage', 'local_eventcalendar'),
                ];
                continue;
            }

            foreach (glob($langdir . '/*.html') as $htmlfile) {
                $identifier = basename($htmlfile, '.html');

                if (!preg_match('/^[a-z0-9_-]+$/i', $identifier)) {
                    $result->skipped[] = (object) [
                        'identifier' => "{$langentry}/{$identifier}",
                        'reason' => get_string('emailtplscaninvalidname', 'local_eventcalendar'),
                    ];
                    continue;
                }

                // Already imported: the scan never touches an existing template.
                if (email_template::get_record(['language' => $langentry, 'identifier' => $identifier])) {
                    continue;
                }

                $metadatafile = $langdir . '/' . $identifier . '.json';

                if (!is_file($metadatafile)) {
                    $result->skipped[] = (object) [
                        'identifier' => "{$langentry}/{$identifier}",
                        'reason' => get_string('emailtplscanmissingmetadata', 'local_eventcalendar'),
                    ];
                    continue;
                }

                $metadata = json_decode(file_get_contents($metadatafile));

                if ($metadata === null || empty($metadata->name)) {
                    $result->skipped[] = (object) [
                        'identifier' => "{$langentry}/{$identifier}",
                        'reason' => get_string('emailtplscaninvalidmetadata', 'local_eventcalendar'),
                    ];
                    continue;
                }

                $content = file_get_contents($htmlfile);

                $newtemplate = new email_template(0);
                $newtemplate->set('name', $metadata->name);
                $newtemplate->set('language', $langentry);
                $newtemplate->set('identifier', $identifier);
                $newtemplate->set('content', $content);
                $newtemplate->create();

                // The first imported template becomes the default, so a fallback always exists.
                if (!$DB->record_exists('local_eventcalendar_emailtpl', ['isdefault' => 1])) {
                    $newtemplate->set_as_default();
                }

                $result->created[] = $metadata->name;
            }
        }

        return $result;
    }
}
