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
 * Repository of the standard content packs.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\notification;
use local_eventcalendar\persistent\notification_set;

/**
 * Repository of the standard content packs (default notifications and day contents, by language and template).
 *
 * On disk: content_packs/{language}/{template_key}.json, one folder per language and one file per template.
 * Adding a language or template only requires a new file. File format:
 * {
 *   "language": "it",
 *   "template_key": "advent",
 *   "version": 1,
 *   "tagline": "Short text below the banner",
 *   "subjects": ["Email subject 1", "Email subject 2"],
 *   "notifications": ["<p>...</p>", "<p>...</p>"],
 *   "restday_daycontents": ["<p>...</p>", "<p>...</p>"],
 *   "daycontents": ["<p>...</p>", "<p>...</p>"],
 *   "daycontent_first": ["<p>...</p>"],
 *   "daycontent_last": ["<p>...</p>"],
 *   "ctas": ["Button text 1", "Button text 2"],
 *   "cta_first": ["First day button text"],
 *   "cta_last": []
 * }
 *
 * The pack used by a calendar is the one with the language chosen in the calendar (field "language", one of
 * the languages returned by get_languages()) and the template of the calendar: see get_for_calendar(). The
 * language of the user who is working on the calendar is never used.
 *
 * The tagline is the only single value (not a pool): it is applied to the calendar by
 * calendar_generator::sync_extend() only if the field is still empty. Absent means no text.
 *
 * The restday_daycontents pool replaces the generic day contents on weekly rest days. It does not hold
 * notifications and is not part of the version-based sync, so changing it needs no version increase.
 *
 * The subjects, daycontent_first/last and ctas/cta_first/cta_last sections are optional. An empty CTA
 * pool is a valid case and means no button for that day.
 *
 * The version field (integer, default 1) allows resyncing already imported sets: see sync_imported_packs().
 *
 * Notifications are imported into the database only on explicit admin action. Day contents are read
 * directly from the packs when a calendar is generated.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_pack_repository {
    /**
     * Returns all packs available on disk, indexed by packkey ("language:template_key", e.g. "it:advent").
     *
     * @return \stdClass[] packkey => decoded JSON object (with the extra ->packkey and ->version properties)
     */
    public static function get_all(): array {
        global $CFG;

        $packs = [];
        $basedir = $CFG->dirroot . '/local/eventcalendar/content_packs';

        if (!is_dir($basedir)) {
            debugging("content_packs folder not found: {$basedir}", DEBUG_DEVELOPER);
            return $packs;
        }

        foreach (scandir($basedir) as $langentry) {
            if ($langentry === '.' || $langentry === '..') {
                continue;
            }

            $langdir = $basedir . '/' . $langentry;

            if (!is_dir($langdir)) {
                continue;
            }

            foreach (glob($langdir . '/*.json') as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content);

                if ($data === null || empty($data->template_key) || empty($data->language)) {
                    debugging("Invalid or incomplete content pack file: {$file}", DEBUG_DEVELOPER);
                    continue;
                }

                $data->version = (int) ($data->version ?? 1);
                $data->notifications = $data->notifications ?? [];
                $data->daycontents = $data->daycontents ?? [];
                // Optional sections default to empty so that packs without them stay compatible.
                // Empty CTA pools are valid and mean no button for that day.
                $data->daycontent_first = $data->daycontent_first ?? [];
                $data->daycontent_last = $data->daycontent_last ?? [];
                $data->ctas = $data->ctas ?? [];
                $data->cta_first = $data->cta_first ?? [];
                $data->cta_last = $data->cta_last ?? [];
                // The tagline is a single value, not a pool.
                $data->tagline = $data->tagline ?? '';
                // Pool of HTML contents for the weekly rest days (not notifications).
                $data->restday_daycontents = $data->restday_daycontents ?? [];
                $data->packkey = $data->language . ':' . $data->template_key;

                $packs[$data->packkey] = $data;
            }
        }

        ksort($packs);

        return $packs;
    }

    /**
     * Returns a single pack by its packkey.
     *
     * @param string $packkey format "language:template_key", e.g. "it:advent"
     * @return \stdClass|null null if not found
     */
    public static function get(string $packkey): ?\stdClass {
        $all = self::get_all();

        return $all[$packkey] ?? null;
    }

    /**
     * Returns the name of a pack to show to the user, for example "Advent calendar (English)".
     *
     * The packs are identified by technical keys (template key and language code). The name is built from the
     * name of the template, translatable through the language file, and from the name of the language in the
     * language list of Moodle. A template or a language code unknown to the site is shown as it is, so the
     * name is always available.
     *
     * @param \stdClass $pack pack as returned by get_all() or get()
     * @return string
     */
    public static function get_label(\stdClass $pack): string {
        $template = template_repository::get((string) $pack->template_key);
        $languages = get_string_manager()->get_list_of_languages();
        $language = (string) $pack->language;

        return get_string('packlabel', 'local_eventcalendar', (object) [
            'template' => $template ? $template->name : (string) $pack->template_key,
            'language' => $languages[$language] ?? \core_text::strtoupper($language),
        ]);
    }

    /**
     * Returns the languages for which at least one content pack exists.
     *
     * Used to fill the language select of the calendar form and to validate the language of a calendar.
     * The names come from the language list of Moodle; a code unknown to that list is shown in capitals.
     *
     * @return string[] language code => name to display, sorted by code
     */
    public static function get_languages(): array {
        $codes = [];

        foreach (self::get_all() as $pack) {
            $codes[(string) $pack->language] = true;
        }

        ksort($codes);

        $known = get_string_manager()->get_list_of_languages();
        $languages = [];

        foreach (array_keys($codes) as $code) {
            $languages[$code] = $known[$code] ?? \core_text::strtoupper($code);
        }

        return $languages;
    }

    /**
     * Returns the pack used by a calendar.
     *
     * The pack is the one of the template of the calendar in the language chosen for the calendar. A calendar
     * without a template or without a language, or without a pack for that combination, has no pack: the texts
     * normally taken from the pack stay empty and the "choose from the pack" selects are not shown.
     *
     * @param calendar $calendar
     * @return \stdClass|null null if the calendar has no pack
     */
    public static function get_for_calendar(calendar $calendar): ?\stdClass {
        $templatekey = (string) $calendar->get('templatekey');
        $language = (string) $calendar->get('language');

        if ($templatekey === '' || $language === '') {
            return null;
        }

        return self::get($language . ':' . $templatekey);
    }

    /**
     * Returns the packs on disk that have not been imported yet (tracked through notifset.sourcepack).
     *
     * @return \stdClass[] packkey => pack object
     */
    public static function get_available_for_import(): array {
        global $DB;

        $all = self::get_all();

        $imported = $DB->get_fieldset_select(
            'local_eventcalendar_notifset',
            'sourcepack',
            'sourcepack IS NOT NULL'
        );

        foreach ($imported as $packkey) {
            unset($all[$packkey]);
        }

        return $all;
    }

    /**
     * Resyncs the imported notification sets whose pack version on disk is newer than the saved one.
     *
     * Only imported sets (sourcepack set) are updated; sets created from scratch are never touched.
     *
     * @return \stdClass ->updated (string[] names of updated sets), ->skipped (objects {name, reason})
     */
    public static function sync_imported_packs(): \stdClass {
        global $DB;

        $result = new \stdClass();
        $result->updated = [];
        $result->skipped = [];

        $importedsets = $DB->get_records_select(
            'local_eventcalendar_notifset',
            'sourcepack IS NOT NULL'
        );

        foreach ($importedsets as $record) {
            $set = new notification_set(0, $record);
            $outcome = self::sync_set($set);

            if ($outcome === 'updated') {
                $result->updated[] = $set->get('name');
            } else if ($outcome !== null) {
                $result->skipped[] = (object) ['name' => $set->get('name'), 'reason' => $outcome];
            }
        }

        return $result;
    }

    /**
     * Resyncs a single imported set if the pack version on disk is newer than the saved one.
     *
     * Content is fully replaced. This is safe because imported sets are locked against editing
     * (notification_set::is_locked()), so there are no local customizations to preserve.
     *
     * @param notification_set $set must have sourcepack set
     * @return string|null 'updated' if updated, a reason text if skipped, null if already up to date
     */
    public static function sync_set(notification_set $set): ?string {
        $pack = self::get($set->get('sourcepack'));

        if (!$pack) {
            // The pack file no longer exists on disk: nothing to resync from.
            return get_string('packnotfoundondisk', 'local_eventcalendar');
        }

        $currentversion = (int) ($set->get('sourcepackversion') ?? 0);

        if ($pack->version <= $currentversion) {
            // Already up to date (or newer): nothing to do.
            return null;
        }

        $context = \context_system::instance();
        $fs = get_file_storage();

        // Replace all notifications of the set (both title and body pools) and their images, then recreate
        // them from the updated pack.
        foreach ($set->get_notifications() as $oldnotification) {
            $fs->delete_area_files(
                $context->id,
                'local_eventcalendar',
                'notificationmessage',
                $oldnotification->get('id')
            );
            $oldnotification->delete();
        }

        self::create_notifications($set, $pack);

        $set->set('sourcepackversion', $pack->version);
        $set->update();

        return 'updated';
    }

    /**
     * Creates the notifications of a set from a pack: one title for each subject and one body for each
     * notification of the pack, in the order of the file.
     *
     * The existing notifications of the set are not touched: the caller removes them first when they must be
     * replaced (see sync_set()). The texts of the pack are saved as they are, without files.
     *
     * @param notification_set $set The set, already saved.
     * @param \stdClass $pack The pack, as returned by get().
     */
    public static function create_notifications(notification_set $set, \stdClass $pack): void {
        foreach ($pack->subjects as $titletext) {
            $newtitle = new notification(0);
            $newtitle->set('setid', $set->get('id'));
            $newtitle->set('kind', 'title');
            $newtitle->set('defaultmessage', $titletext);
            $newtitle->set('defaultmessageformat', FORMAT_PLAIN);
            $newtitle->create();
        }

        foreach ($pack->notifications as $text) {
            $newnotification = new notification(0);
            $newnotification->set('setid', $set->get('id'));
            $newnotification->set('kind', 'body');
            $newnotification->set('defaultmessage', $text);
            $newnotification->set('defaultmessageformat', FORMAT_HTML);
            $newnotification->create();
        }
    }
}
