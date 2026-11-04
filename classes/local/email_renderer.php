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
 * Builds the notification email of a day using the email template of its calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;
use local_eventcalendar\persistent\email_template;

/**
/**
 * Builds the notification email of a day using the email template of its calendar.
 *
 * Replaces the {{...}} placeholders of the template with real values. Used by the preview
 * page and by the notification sending task.
 *
 * The images of the plugin are addressed through pluginfile.php, which needs a session: the sending
 * (notification_dispatcher::add_file_tokens()) gives them the token of the recipient, so that they load in
 * an external mail client too. The preview keeps the plain addresses, read in the session of the manager.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_renderer {
    /**
     * Builds the full HTML of the notification email for a day.
     * @param day $day
     * @param calendar $calendar Calendar the day belongs to
     * @param \stdClass|null $recipientuser Recipient used for {{firstname}}; null means the current user
     * @return string|null Ready-to-send HTML, or null when there are no email templates
     */
    public static function render(day $day, calendar $calendar, ?\stdClass $recipientuser = null): ?string {
        global $CFG, $SITE, $USER;

        // The template chosen for the calendar, or the default one (see calendar::get_email_template()).
        $emailtemplate = $calendar->get_email_template();

        if (!$emailtemplate) {
            return null;
        }

        $recipientuser = $recipientuser ?? $USER;
        $context = \context_system::instance();

        // The link points to the day page, which needs the calendar id and the day number.
        $linkurl = new \moodle_url('/local/eventcalendar/view_day.php', [
            'id' => $calendar->get('id'),
            'day' => $day->get('daynumber'),
        ]);

        $replacements = [
            '{{content}}' => format_text(
                $day->get('notifbody') ?? '',
                $day->get('notifbodyformat'),
                ['context' => $context]
            ),
            // The first name is plain text: it is escaped like any other text value inserted into the HTML.
            '{{firstname}}' => s($recipientuser->firstname ?? ''),
            '{{calendarname}}' => format_string($calendar->get('name')),
            '{{daynumber}}' => (string) $day->get('daynumber'),
            '{{linkurl}}' => $linkurl->out(false),
            '{{unsubscribeurl}}' => unsubscribe::get_url((int) $calendar->get('id'), (int) $recipientuser->id)->out(false),
            '{{sitename}}' => format_string($SITE->fullname),
            '{{siteurl}}' => $CFG->wwwroot,
            '{{logo}}' => self::build_image_html($calendar->get_logo_url(), format_string($SITE->fullname)),
            '{{banner}}' => self::build_image_html($calendar->get_banner_url(), ''),
            // Use gmdate() rather than date() for UTC consistency across the plugin.
            '{{year}}' => gmdate('Y'),
        ];

        return strtr($emailtemplate->get('content'), $replacements);
    }

    /**
     * Builds the image tag for the {{logo}} and {{banner}} placeholders.
     *
     * The logo is meaningful, so it gets the site name as alt text; the banner is decorative
     * and gets an empty alt.
     *
     * @param \moodle_url|null $url
     * @param string $alt Alternative text (empty for purely decorative images)
     * @return string Complete img tag, or an empty string when $url is null
     */
    private static function build_image_html(?\moodle_url $url, string $alt): string {
        if (!$url) {
            return '';
        }

        $attributes = [
            'src' => $url->out(false),
            'style' => 'max-width:100%; height:auto; display:inline-block;',
        ];

        if ($alt === '') {
            $attributes['alt'] = '';
            $attributes['role'] = 'presentation';
        } else {
            $attributes['alt'] = $alt;
        }

        return \html_writer::empty_tag('img', $attributes);
    }
}
