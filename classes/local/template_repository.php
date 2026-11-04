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
 * Repository of the calendar templates.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Repository of the calendar templates.
 *
 * Templates are static JSON files in templates/definitions, read at runtime to fill the
 * template select of the calendar form. The date_rule.type value matches a date resolver
 * type (fixed, computed or lookup_table).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_repository {
    /**
     * Returns all the available templates, indexed by template_key.
     *
     * @return \stdClass[] template_key => object decoded from the JSON.
     */
    public static function get_all(): array {
        global $CFG;

        $templates = [];
        $dir = $CFG->dirroot . '/local/eventcalendar/templates/definitions';

        if (!is_dir($dir)) {
            return $templates;
        }

        foreach (glob($dir . '/*.json') as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content);

            if ($data === null || empty($data->template_key)) {
                // Malformed file or missing template_key: report it in debug mode and keep loading the others.
                debugging("Invalid or incomplete template JSON file: {$file}", DEBUG_DEVELOPER);
                continue;
            }

            // The displayed name comes from the language file so that it is translatable;
            // the name in the JSON is the fallback.
            $stringid = 'template_' . $data->template_key;
            if (get_string_manager()->string_exists($stringid, 'local_eventcalendar')) {
                $data->name = get_string($stringid, 'local_eventcalendar');
            }

            $templates[$data->template_key] = $data;
        }

        ksort($templates);

        return $templates;
    }

    /**
     * Returns a single template given its template_key.
     *
     * @param string $templatekey
     * @return \stdClass|null Null if not found.
     */
    public static function get(string $templatekey): ?\stdClass {
        $all = self::get_all();

        return $all[$templatekey] ?? null;
    }

    /**
     * Computes the default values of the calendar form fields for a template.
     *
     * The anchor date is resolved for the current year through the date resolver.
     * The icon set is not defaulted. The description is always filled with the template
     * name and year, forcehide is 0 so that dates drive the activation, and
     * enddisplaydate is the end date plus 20 days when the anchor date can be computed.
     *
     * @param \stdClass $template Template object as returned by get() or get_all().
     * @return \stdClass Default values, keyed by form field name.
     */
    public static function compute_calendar_defaults(\stdClass $template): \stdClass {
        $defaults = new \stdClass();
        $defaults->templatekey = $template->template_key;
        $defaults->name = $template->name;
        $defaults->progressiveunlock = !empty($template->progressive_unlock_default) ? 1 : 0;

        // Use gmdate() because the resolver works in UTC: date() could return a different
        // year around New Year's midnight on a server in another timezone.
        $currentyear = (int) gmdate('Y');

        // Always set, even if the anchor date cannot be resolved below.
        $defaults->intro = $template->name . ' ' . $currentyear;

        // The tagline is not set here: the definitions are language independent, and the
        // text comes from the content pack when the calendar is saved.

        // Template calendars follow the automatic activation instead of being forced hidden.
        $defaults->forcehide = 0;

        $params = get_object_vars($template->date_rule);
        $type = $params['type'];
        unset($params['type']);

        // The lookup_table resolver filters the shared lookup table by template key.
        if ($type === 'lookup_table') {
            $params['templatekey'] = $template->template_key;
        }

        try {
            $resolver = \local_eventcalendar\local\date_resolver\factory::create($type, $params);
            $anchor = $resolver->resolve($currentyear);

            $dayscount = max(1, (int) ($template->days_count ?? 1));

            $defaults->enddate = $anchor;
            $defaults->startdate = $anchor - (($dayscount - 1) * DAYSECS);

            // Grace period of 20 days after the end date.
            $defaults->enddisplaydate = $anchor + (20 * DAYSECS);
        } catch (\Throwable $e) {
            // No default dates, for example when the lookup table has no data for the current
            // year. The administrator sets them manually.
            debugging(
                'Unable to compute default dates from template ' .
                    "{$template->template_key}: " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        return $defaults;
    }
}
