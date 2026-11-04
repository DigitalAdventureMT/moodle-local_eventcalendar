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
 * Service for the management of calendars.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\calendar_deleting;
use local_eventcalendar\hook\calendar_saved;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Service for the management of calendars.
 *
 * It holds the logic that was written directly in the pages, so that every caller behaves in the same way
 * and the other plugins can take part through hooks. The pages only check permissions and parameters and
 * show the result.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_manager {
    /** Calendar properties that the calendar form sets, copied from the submitted data onto the persistent. */
    private const PROPERTIES = [
        'name', 'templatekey', 'language', 'intro', 'tagline', 'startdate', 'enddate', 'enddisplaydate',
        'progressiveunlock', 'iconsetid', 'notificationsetid', 'forcehide',
        'restdayenabled', 'restdayweekday', 'emailtemplateid',
    ];

    /** Properties whose empty form value ('' or 0, meaning "none") is stored as null. */
    private const NULLABLE_PROPERTIES = ['templatekey', 'language', 'notificationsetid', 'emailtemplateid'];

    /**
     * Creates or updates a calendar from the data of the calendar form, with its days and its files.
     *
     * The calendar is new when it has no id yet. The data must already be validated by the form. The steps are:
     *
     *  1. the empty values of the optional selects are normalised to null;
     *  2. for an existing calendar, the days that the new dates would leave out are listed and the previous
     *     weekly rest day settings are kept, because both need the values from before the change;
     *  3. the properties are copied onto the persistent, which is created or updated;
     *  4. the days are generated (new calendar) or aligned to the new settings and dates (existing calendar):
     *     rest day realignment, deletion of the days left out, generation of the missing ones. A failure of
     *     this step is reported in the debug output and does not undo the saved calendar;
     *  5. the calendar_saved hook is dispatched.
     *
     * The two gallery choices are not form elements, so the caller reads them from the request: the banner
     * choice is the id of a preloaded banner, the header and footer choice is 'none' or the id of a preloaded
     * pair. Any other value (for example an option added by another plugin) leaves no preloaded element. The
     * choices are added to the data received by the hook (bannerchoice, headerfooterchoice), so that the plugins
     * that added options to the galleries can save what they need.
     *
     * It checks no capability and shows nothing: the caller does both.
     *
     * @param calendar $calendar The calendar to save: new (id 0) or existing.
     * @param \stdClass $data Validated data returned by the calendar form.
     * @param string $bannerchoice Banner gallery choice.
     * @param string $headerfooterchoice Header and footer gallery choice.
     * @return \stdClass With 'restoverwritten': number of days whose content was rewritten by the rest day
     *                   realignment (always 0 for a new calendar).
     */
    public static function save(
        calendar $calendar,
        \stdClass $data,
        string $bannerchoice = '',
        string $headerfooterchoice = 'none'
    ): \stdClass {
        $isnew = empty($calendar->get('id'));
        $result = (object) ['restoverwritten' => 0];

        // The caller's object is left untouched: the normalised copy is what the hook receives.
        $data = clone $data;
        foreach (self::NULLABLE_PROPERTIES as $property) {
            if (empty($data->{$property})) {
                $data->{$property} = null;
            }
        }

        // Values from before the change, needed by the alignment of an existing calendar.
        $syncpreview = null;
        $oldrestenabled = false;
        $oldrestweekday = 0;
        if (!$isnew) {
            // If the new dates shorten the calendar, the form validation has already required an explicit
            // confirmation, so the days listed here can be deleted without asking again.
            $syncpreview = calendar_generator::sync_preview(
                (int) $calendar->get('id'),
                (int) $data->startdate,
                (int) $data->enddate
            );
            $oldrestenabled = (bool) $calendar->get('restdayenabled');
            $oldrestweekday = (int) $calendar->get('restdayweekday');
        }

        foreach (self::PROPERTIES as $property) {
            if (property_exists($data, $property)) {
                $calendar->set($property, $data->{$property});
            }
        }

        // Banner and header/footer: a number is the id of a preloaded element, any other value means none.
        $calendar->set('bannerid', ctype_digit($bannerchoice) ? (int) $bannerchoice : null);
        $calendar->set('headerfooterid', ctype_digit($headerfooterchoice) ? (int) $headerfooterchoice : null);
        $data->bannerchoice = $bannerchoice;
        $data->headerfooterchoice = $headerfooterchoice;

        if ($isnew) {
            $calendar->create();
        } else {
            $calendar->update();
            $result->restoverwritten = self::align_days($calendar, $syncpreview, $oldrestenabled, $oldrestweekday);
        }

        // A new calendar has no days yet, so the generator creates them all; for an existing one it adds the
        // missing days after the alignment above.
        self::generate_days($calendar, $isnew);

        \core\di::get(\core\hook\manager::class)->dispatch(new calendar_saved($calendar, $data, $isnew));

        return $result;
    }

    /**
     * Aligns the existing days of an updated calendar: rest day realignment and deletion of the days left out.
     *
     * The order matters: the days outside the new range are deleted before the generator renumbers the
     * remaining ones and creates the missing ones, otherwise the numbers would clash on the unique index
     * (calendarid, daynumber).
     *
     * @param calendar $calendar The calendar, already updated.
     * @param \stdClass $syncpreview Result of calendar_generator::sync_preview() computed before the update.
     * @param bool $oldrestenabled Previous value of restdayenabled.
     * @param int $oldrestweekday Previous value of restdayweekday.
     * @return int Number of days whose content was rewritten by the rest day realignment.
     */
    private static function align_days(
        calendar $calendar,
        \stdClass $syncpreview,
        bool $oldrestenabled,
        int $oldrestweekday
    ): int {
        // Content, CTA and links of the days involved are rewritten; notifications are never touched. It runs
        // before the generation, as the new days are already created with the right settings.
        $restoverwritten = 0;
        try {
            $restoverwritten = calendar_generator::apply_restday_change($calendar, $oldrestenabled, $oldrestweekday);
        } catch (\Throwable $e) {
            debugging('Rest day realignment failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        calendar_generator::delete_days($syncpreview->extradays);

        return $restoverwritten;
    }

    /**
     * Generates the missing days of a calendar. A failure must not undo the saved calendar.
     *
     * @param calendar $calendar
     * @param bool $isnew True for a calendar just created (only changes the message of a failure).
     */
    private static function generate_days(calendar $calendar, bool $isnew): void {
        try {
            calendar_generator::sync_extend($calendar);
        } catch (\Throwable $e) {
            debugging(
                ($isnew ? 'Automatic day generation failed: ' : 'Automatic day synchronisation failed: ') . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Permanently deletes a calendar with everything that depends on it.
     *
     * The database does not enforce foreign keys, so every related record is removed explicitly to avoid
     * orphan rows. The order is:
     *
     *  1. the calendar_deleting hook, while all the data still exists;
     *  2. the days, with their files, opening logs and sent notifications (this dispatches days_deleting);
     *  3. opening logs, sent notifications, subscriptions and access rules of the calendar;
     *  4. the calendar record.
     *
     * The calendar has no files of its own in this plugin: the plugins that add them delete them in the hook.
     *
     * It asks for no confirmation and checks no capability: the caller must have done both.
     *
     * @param calendar $calendar The calendar to delete.
     */
    public static function delete(calendar $calendar): void {
        global $DB;

        $calendarid = (int) $calendar->get('id');

        // The hook comes first: callbacks can still read the calendar and its days.
        \core\di::get(\core\hook\manager::class)->dispatch(new calendar_deleting($calendarid));

        // The days go through the generator, so the days_deleting hook is dispatched in the same way as
        // when days are removed because the dates of a calendar have been shortened.
        calendar_generator::delete_days(day::get_records(['calendarid' => $calendarid]));

        $DB->delete_records('local_eventcalendar_log', ['calendarid' => $calendarid]);
        $DB->delete_records('local_eventcalendar_sent', ['calendarid' => $calendarid]);
        $DB->delete_records('local_eventcalendar_subscr', ['calendarid' => $calendarid]);
        access_rules::delete_for_calendar($calendarid);

        $calendar->delete();
    }
}
