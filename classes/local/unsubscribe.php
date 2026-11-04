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
 * Unsubscribe links of the notification emails.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Unsubscribe links of the notification emails.
 *
 * The link opens unsubscribe.php without login, so that it works from any email client. The link
 * carries a token, an HMAC of calendar and user computed with a secret of the site: only who received
 * the email can unsubscribe that user from that calendar. The secret is created at first use and
 * stored in the plugin configuration.
 *
 * The same link is sent in the List-Unsubscribe email headers, with the one-click variant (RFC 8058),
 * so that email clients can show their own unsubscribe button.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unsubscribe {
    /** Name of the plugin setting with the secret used for the tokens. */
    protected const SECRET_CONFIG = 'unsubscribesecret';

    /**
     * Unsubscribe URL of a user for a calendar.
     *
     * @param int $calendarid
     * @param int $userid
     * @return \moodle_url
     */
    public static function get_url(int $calendarid, int $userid): \moodle_url {
        return new \moodle_url('/local/eventcalendar/unsubscribe.php', [
            'calendar' => $calendarid,
            'user' => $userid,
            'token' => self::get_token($calendarid, $userid),
        ]);
    }

    /**
     * Email headers that tell email clients how to unsubscribe with one click.
     *
     * @param int $calendarid
     * @param int $userid
     * @return string[]
     */
    public static function get_headers(int $calendarid, int $userid): array {
        return [
            'List-Unsubscribe: <' . self::get_url($calendarid, $userid)->out(false) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];
    }

    /**
     * Checks the token of an unsubscribe link.
     *
     * @param int $calendarid
     * @param int $userid
     * @param string $token
     * @return bool
     */
    public static function is_valid_token(int $calendarid, int $userid, string $token): bool {
        return hash_equals(self::get_token($calendarid, $userid), $token);
    }

    /**
     * Token of the unsubscribe link of a user for a calendar.
     *
     * @param int $calendarid
     * @param int $userid
     * @return string
     */
    protected static function get_token(int $calendarid, int $userid): string {
        return hash_hmac('sha256', $calendarid . ':' . $userid, self::get_secret());
    }

    /**
     * Secret of the site used for the tokens, created at first use.
     *
     * @return string
     */
    protected static function get_secret(): string {
        $secret = (string) get_config('local_eventcalendar', self::SECRET_CONFIG);
        if ($secret === '') {
            $secret = random_string(64);
            set_config(self::SECRET_CONFIG, $secret, 'local_eventcalendar');
        }

        return $secret;
    }
}
