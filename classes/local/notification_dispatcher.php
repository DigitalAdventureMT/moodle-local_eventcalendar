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
 * Sending of the daily notifications to the subscribed users.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Sends the daily notifications to the subscribed users.
 *
 * Called by the scheduled task classes/task/send_notifications.php, which is
 * only a wrapper: the logic is here, with the current time passed as a
 * parameter so it can be tested without waiting for cron.
 *
 * Rules:
 * - Each subscribed user receives the notification of a day once, at a fixed
 *   morning hour in the user's own timezone (SEND_HOUR), not at the midnight
 *   of the unlock, because the texts are written for a morning reading.
 * - The notification is valid only during the day it represents, in the
 *   user's timezone: from the send hour until the user's midnight. A cron
 *   delay or a day marked ready later is recovered within the same day; an
 *   expired notification is never sent.
 * - A user who subscribes after the calendar has started receives only the
 *   later notifications: if the subscription is later than the send time of
 *   a day, that notification is skipped.
 * - "isready" does not affect sending. A day with neither title nor body is
 *   never sent, as there would be nothing to say.
 * - Only if the calendar is visible to the user (manual visibility and
 *   dates, per timezone) and the user satisfies the access rules
 *   (classes/local/access_rules.php). A user who loses access stays
 *   subscribed but receives nothing; if access is regained, sending resumes.
 * - Deleted or suspended users receive nothing.
 * - The title may contain the {{name}} placeholder (calendar name), which is
 *   replaced here.
 *
 * - Each run sends at most the number of notifications set in the plugin
 *   settings, to spread the emails over time; the others are sent by the
 *   next runs, within the same day.
 * - Users whose email address Moodle would refuse (invalid, or too many
 *   bounces) receive nothing: the case is written in the task log.
 * - A failed sending is retried once, by the next run; the failures are
 *   written in the task log.
 *
 * How the message is delivered (always email, in-app notification unless the
 * user disabled it) is decided by the message provider in db/messages.php.
 * Every email carries an unsubscribe link (classes/local/unsubscribe.php),
 * also in the List-Unsubscribe headers.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_dispatcher {
    /** Local hour (0-23) at which the notification of a day is sent. */
    public const SEND_HOUR = 8;

    /** Default maximum number of notifications sent by each run of the task. */
    public const DEFAULT_MAX_EMAILS = 500;

    /** Sending attempts of a notification: a failed sending is retried once, by the next run. */
    public const MAX_ATTEMPTS = 2;

    /** Test send result: email sent. */
    public const TEST_SENT = 'sent';

    /** Test send result: the day has neither title nor body, nothing to send. */
    public const TEST_EMPTY = 'empty';

    /** Test send result: Moodle could not send the email. */
    public const TEST_FAILED = 'failed';

    /**
     * Sends the notifications due at the given time.
     *
     * The notifications due now, for all calendars, are collected first and then sent in order, up to
     * the limit set in the plugin settings (maxemailsperrun). The notifications left out are sent by
     * the next runs, until the end of the day of each user. The order is:
     * - first the notifications due for the longest time (earliest send time);
     * - for the same send time, a pseudo-random order that changes every day, so that the same users
     *   are not always the last ones to receive the notification.
     *
     * @param int $now Current time (timestamp).
     * @return int Number of notifications sent.
     */
    public static function dispatch(int $now): int {
        global $DB, $PAGE;

        // Only days whose UTC date is within two days of now. In any timezone (UTC-12 to UTC+14) the send
        // window of a day falls within 36 hours of its UTC date. Without such days nothing is due, and the
        // run ends without reading subscriptions and users.
        $days = day::get_records_select(
            'opendate >= :windowstart AND opendate <= :windowend',
            ['windowstart' => $now - 2 * DAYSECS, 'windowend' => $now + 2 * DAYSECS]
        );
        if (empty($days)) {
            return 0;
        }

        $daysbycalendar = [];
        foreach ($days as $day) {
            $daysbycalendar[(int) $day->get('calendarid')][] = $day;
        }

        // Subscriptions and users of those calendars only. Users are read with a subquery rather than a
        // list of ids, which some databases limit in length; deleted and suspended users are left out,
        // as they receive nothing.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($daysbycalendar), SQL_PARAMS_NAMED);
        $subscriptions = $DB->get_records_select('local_eventcalendar_subscr', "calendarid $insql", $inparams);
        if (empty($subscriptions)) {
            return 0;
        }

        $users = $DB->get_records_select(
            'user',
            "deleted = 0 AND suspended = 0 AND id IN (SELECT userid FROM {local_eventcalendar_subscr} WHERE calendarid $insql)",
            $inparams
        );
        // The profile description is not used and may be long: it is dropped to save memory.
        foreach ($users as $user) {
            unset($user->description);
        }

        // The page context may not be set in a task, but the text filters used by the renderers need it.
        $PAGE->set_context(\context_system::instance());

        // Access rules are reloaded on every run, as the task process may stay alive for a long time.
        access_rules::reset_cache();

        $bycalendar = [];
        foreach ($subscriptions as $subscription) {
            $bycalendar[(int) $subscription->calendarid][] = $subscription;
        }

        $queue = [];
        foreach ($bycalendar as $calendarid => $calendarsubscriptions) {
            $queue = array_merge(
                $queue,
                self::get_due_notifications($calendarid, $daysbycalendar[$calendarid], $calendarsubscriptions, $users, $now)
            );
        }

        usort($queue, function (\stdClass $a, \stdClass $b): int {
            return [$a->sendat, $a->order] <=> [$b->sendat, $b->order];
        });

        $limit = self::get_max_emails();
        $context = \context_system::instance();
        $sent = 0;
        $attempts = 0;

        foreach ($queue as $item) {
            if ($limit > 0 && $attempts >= $limit) {
                break;
            }

            // Moodle would refuse the email: the notification is recorded as skipped (no attempts), so that
            // the in-app notification is not created again by every run while the email keeps failing.
            if (!self::can_receive_email($item->user)) {
                mtrace('local_eventcalendar: notification not sent, invalid email address of user ' . $item->user->id);
                self::record($item, null, 0);
                continue;
            }

            // A day with nothing to say is not sent and not recorded: it is sent if a text is added later
            // in the day.
            $content = self::build_content($item->calendar, $item->day, $item->user, $context);
            if ($content === null) {
                continue;
            }

            $attempts++;
            $attempt = $item->attempts + 1;
            if (static::send_message(self::build_message($item->calendar, $item->day, $item->user, $content))) {
                self::record($item, $now, $attempt);
                $sent++;
                continue;
            }

            // Failed: retried once by the next run, then given up. Every attempt creates the in-app
            // notification again, so a single retry limits the duplicates to one.
            self::record($item, null, $attempt);
            if ($attempt >= self::MAX_ATTEMPTS) {
                mtrace('local_eventcalendar: notification not sent after ' . $attempt . ' attempts, user '
                    . $item->user->id . ', day ' . $item->day->get('id'));
            } else {
                mtrace('local_eventcalendar: notification not sent, retried by the next run, user '
                    . $item->user->id . ', day ' . $item->day->get('id'));
            }
        }

        return $sent;
    }

    /**
     * Maximum number of notifications sent by a run, from the plugin settings; 0 means no limit.
     *
     * @return int
     */
    public static function get_max_emails(): int {
        $limit = get_config('local_eventcalendar', 'maxemailsperrun');

        return ($limit === false || $limit === '') ? self::DEFAULT_MAX_EMAILS : max(0, (int) $limit);
    }

    /**
     * Whether Moodle would send an email to the user.
     *
     * Moodle does not send emails to invalid addresses, nor to addresses with too many bounces when the
     * bounce handling of the site is enabled. The address may be replaced by the one in the messaging
     * preferences of the user, when the site allows it, as the email output of Moodle does.
     *
     * @param \stdClass $user
     * @return bool
     */
    protected static function can_receive_email(\stdClass $user): bool {
        global $CFG;

        $email = $user->email;
        if (!empty($CFG->messagingallowemailoverride)) {
            $preference = clean_param((string) get_user_preferences('message_processor_email_email', '', $user), PARAM_EMAIL);
            if ($preference !== '') {
                $email = $preference;
            }
        }

        return validate_email($email) && !over_bounce_threshold($user);
    }

    /**
     * Records the result of the notification of a day for a user, so that it is not sent again (or is
     * retried once, after a first failure).
     *
     * @param \stdClass $item Item of the queue, with calendar, day, user and the id of its record, if any.
     * @param int|null $timesent Time of sending, null if not sent.
     * @param int $attempts Sending attempts made so far.
     */
    protected static function record(\stdClass $item, ?int $timesent, int $attempts): void {
        global $DB;

        if ($item->sentid) {
            $DB->update_record('local_eventcalendar_sent', (object) [
                'id' => $item->sentid,
                'timesent' => $timesent,
                'attempts' => $attempts,
            ]);
            return;
        }

        try {
            $DB->insert_record('local_eventcalendar_sent', (object) [
                'calendarid' => $item->calendar->get('id'),
                'dayid' => $item->day->get('id'),
                'userid' => $item->user->id,
                'timesent' => $timesent,
                'attempts' => $attempts,
            ]);
        } catch (\dml_write_exception $e) {
            // Already recorded by a parallel run: nothing to do.
            unset($e);
        }
    }

    /**
     * Sends a message through the Moodle messaging system.
     *
     * Separate from the dispatch so that tests can simulate a failed sending.
     *
     * @param \core\message\message $message
     * @return bool True if Moodle sent the message through all its outputs.
     */
    protected static function send_message(\core\message\message $message): bool {
        return (bool) message_send($message);
    }

    /**
     * Returns the notifications of a calendar due at the given time and not yet sent.
     *
     * The cheap checks (already sent, send window, subscription time) come first: the visibility of
     * the calendar and the access rules are checked only for users with a notification due, so that
     * the runs after the morning sending cost little.
     *
     * @param int $calendarid
     * @param day[] $days Days of the calendar near the given time.
     * @param \stdClass[] $subscriptions Subscriptions to that calendar.
     * @param \stdClass[] $users Active user records indexed by id.
     * @param int $now
     * @return \stdClass[] items with calendar, day, user, sendat (send time) and order (position for the same send time)
     */
    private static function get_due_notifications(
        int $calendarid,
        array $days,
        array $subscriptions,
        array $users,
        int $now
    ): array {
        global $DB;

        if (!calendar::record_exists($calendarid)) {
            return [];
        }

        $calendar = new calendar($calendarid);

        // Notifications already handled for these days: sent, skipped, or failed. Only a notification that
        // failed once is attempted again.
        [$insql, $inparams] = $DB->get_in_or_equal(array_map(fn ($d) => $d->get('id'), $days));
        $records = [];
        $fields = 'id, dayid, userid, timesent, attempts';
        foreach ($DB->get_records_select('local_eventcalendar_sent', "dayid $insql", $inparams, '', $fields) as $row) {
            $records[$row->dayid . ':' . $row->userid] = $row;
        }

        // Notifications due for each user, before the more expensive checks.
        $pending = [];
        foreach ($subscriptions as $subscription) {
            $user = $users[$subscription->userid] ?? null;
            if (!$user) {
                continue;
            }

            foreach ($days as $day) {
                $record = $records[$day->get('id') . ':' . $user->id] ?? null;
                if ($record && ($record->timesent !== null || (int) $record->attempts !== 1)) {
                    continue;
                }

                $sendat = $day->get_local_time(self::SEND_HOUR, $user);
                $dayend = $day->get_local_day_end($user);

                // Too early, or the local day is already over.
                if ($now < $sendat || $now >= $dayend) {
                    continue;
                }

                // Subscribed after the send time: the notification is skipped.
                if ((int) $subscription->timesubscribed > $sendat) {
                    continue;
                }

                // The day id changes every day, so the order of users with the same send time changes too.
                $pending[] = (object) [
                    'calendar' => $calendar,
                    'day' => $day,
                    'user' => $user,
                    'sendat' => $sendat,
                    'order' => crc32($day->get('id') . ':' . $user->id),
                    'sentid' => $record ? (int) $record->id : 0,
                    'attempts' => $record ? (int) $record->attempts : 0,
                ];
            }
        }

        if (empty($pending)) {
            return [];
        }

        // Preload the access rule data of the users with a notification due (one query per rule, not per user).
        $userids = array_unique(array_map(fn ($item) => (int) $item->user->id, $pending));
        access_rules::prefetch($calendarid, array_values($userids));

        $allowed = [];
        $due = [];
        foreach ($pending as $item) {
            $userid = (int) $item->user->id;
            if (!isset($allowed[$userid])) {
                $allowed[$userid] = $calendar->is_visible($item->user) && access_rules::user_can_access($calendar, $item->user);
            }
            if ($allowed[$userid]) {
                $due[] = $item;
            }
        }

        return $due;
    }

    /**
     * Builds the notification content of a day for a user: subject, plain text and HTML.
     *
     * Shared by the real send and the test sends, so a test shows exactly
     * what a subscriber would receive.
     *
     * @param calendar $calendar
     * @param day $day May be a copy with unsaved values.
     * @param \stdClass $user Recipient (used for the email template placeholders).
     * @param \context $context
     * @return \stdClass|null ->subject, ->plain, ->html; null if there is nothing to say.
     */
    private static function build_content(calendar $calendar, day $day, \stdClass $user, \context $context): ?\stdClass {
        $name = format_string($calendar->get('name'), true, ['context' => $context]);

        $title = trim(str_replace('{{name}}', $name, (string) $day->get('notiftitle')));
        $body = str_replace('{{name}}', $name, (string) $day->get('notifbody'));

        $bodyhtml = clean_text($body, (int) $day->get('notifbodyformat'));
        $bodyplain = trim(html_to_text($bodyhtml, 0, false));

        // Nothing to say: do not send and do not record it as sent.
        if ($title === '' && $bodyplain === '') {
            return null;
        }

        // Email: the plugin email template, rendered on a copy so the day is not modified. The images of the
        // plugin get the token of the recipient, so that they load also in a mail client, without a session.
        $renderedday = clone $day;
        $renderedday->set('notifbody', $body);
        $emailhtml = self::add_file_tokens(email_renderer::render($renderedday, $calendar, $user) ?? $bodyhtml, (int) $user->id);

        return (object) [
            'subject' => $title !== '' ? $title : $name,
            'plain' => $bodyplain,
            'html' => $emailhtml,
        ];
    }

    /**
     * Gives the addresses of the images of this plugin and of its add-ons a personal token of the recipient.
     *
     * The images (logo and banner of the calendar, images of the notification body) are served by pluginfile.php,
     * which needs a Moodle session: a mail client has none, so they would not load. The same files can be read
     * through tokenpluginfile.php with the token of the user, as Moodle does for the images of the forum emails.
     * The file callbacks still apply their checks, as the recipient: a token opens only what the recipient could
     * see while logged in.
     *
     * Only the files of the system context of this plugin and of its subplugins (components starting with
     * ecaddon_ or ecpack_) are changed: the other addresses (the site logo, which Moodle serves to everyone,
     * external images) are left as they are. The subplugins are recognised by their prefix, not by a list, so
     * any add-on or pack is covered without being named here. Both address forms of Moodle are recognised
     * (with and without slash arguments), and each address is rebuilt with make_pluginfile_url(), so the
     * result follows the configuration of the site.
     *
     * @param string $html HTML of the email.
     * @param int $userid Recipient.
     * @return string
     */
    public static function add_file_tokens(string $html, int $userid): string {
        global $CFG;

        $contextid = (int) \context_system::instance()->id;
        $pattern = '~' . preg_quote($CFG->wwwroot . '/pluginfile.php', '~') . '(/[^"\'\s<>?#]+|\?file=[^"\'\s<>]+)~';

        return preg_replace_callback($pattern, function (array $match) use ($contextid, $userid): string {
            // The file path: the path after the script, or the "file" parameter up to the next parameter.
            $path = $match[1];
            if (strpos($path, '?file=') === 0) {
                $path = substr($path, strlen('?file='));
                $path = preg_split('~&(amp;)?~', $path)[0];
            }
            $parts = explode('/', trim(rawurldecode($path), '/'));

            // Context, component, area, item, optional folders and file name.
            if (count($parts) < 5 || (int) $parts[0] !== $contextid || !self::is_own_component($parts[1])) {
                return $match[0];
            }
            [, $component, $filearea, $itemid] = $parts;
            $filename = array_pop($parts);
            $folders = array_slice($parts, 4);
            $filepath = $folders ? '/' . implode('/', $folders) . '/' : '/';

            $url = \moodle_url::make_pluginfile_url(
                $contextid,
                $component,
                $filearea,
                (int) $itemid,
                $filepath,
                $filename,
                false,
                $userid
            );

            // The address is written inside an HTML attribute, so the separators of its parameters are escaped.
            return $url->out(true);
        }, $html);
    }

    /**
     * Whether a component is this plugin or one of its subplugins (types ecaddon and ecpack).
     *
     * @param string $component Frankenstyle name read from a file address.
     * @return bool
     */
    private static function is_own_component(string $component): bool {
        return $component === 'local_eventcalendar'
            || strpos($component, 'ecaddon_') === 0
            || strpos($component, 'ecpack_') === 0;
    }

    /**
     * Builds the Moodle message of a day for a user.
     *
     * Shared by the real send and the test notification.
     *
     * @param calendar $calendar
     * @param day $day
     * @param \stdClass $user Recipient.
     * @param \stdClass $content Result of build_content().
     * @return \core\message\message
     */
    private static function build_message(
        calendar $calendar,
        day $day,
        \stdClass $user,
        \stdClass $content
    ): \core\message\message {
        $url = new \moodle_url('/local/eventcalendar/view_day.php', [
            'id' => $calendar->get('id'),
            'day' => $day->get('daynumber'),
        ]);

        $message = new \core\message\message();
        $message->courseid = SITEID;
        $message->component = 'local_eventcalendar';
        $message->name = 'daynotification';
        // The sender is a copy of the no-reply user with the unsubscribe headers, read by email_to_user().
        $message->userfrom = clone \core_user::get_noreply_user();
        $message->userfrom->customheaders = unsubscribe::get_headers((int) $calendar->get('id'), (int) $user->id);
        $message->userto = $user;
        $message->subject = $content->subject;
        $message->fullmessage = $content->plain;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $content->html;
        $message->smallmessage = shorten_text($content->plain, 120);
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('notificationcontextname', 'local_eventcalendar');

        return $message;
    }

    /**
     * Sends the email of a day, as a test, to a single user.
     *
     * Only the email (not the in-app notification) is sent, to the given
     * user only: callers always pass the current user. Nothing is recorded as
     * sent, and subscription, visibility, access rules and time are ignored.
     * The email is sent as HTML even if the user prefers plain text, since
     * the purpose is to see the template.
     *
     * @param calendar $calendar
     * @param day $day May be a copy with unsaved values.
     * @param \stdClass $user Recipient (the current user).
     * @return string One of the TEST_* constants.
     */
    public static function send_test_email(calendar $calendar, day $day, \stdClass $user): string {
        $content = self::build_content($calendar, $day, $user, \context_system::instance());

        if ($content === null) {
            return self::TEST_EMPTY;
        }

        $recipient = clone $user;
        $recipient->mailformat = 1;

        $sent = email_to_user($recipient, \core_user::get_noreply_user(), $content->subject, $content->plain, $content->html);

        return $sent ? self::TEST_SENT : self::TEST_FAILED;
    }

    /**
     * Returns the message, in the current language, for the result of a test send.
     *
     * @param string $result One of the TEST_* constants.
     * @param \stdClass $user Recipient, whose address is quoted.
     * @return string Text ready to display (already escaped).
     */
    public static function get_test_email_message(string $result, \stdClass $user): string {
        switch ($result) {
            case self::TEST_SENT:
                return get_string('testemailsent', 'local_eventcalendar', s($user->email));
            case self::TEST_EMPTY:
                return get_string('testnothingtosend', 'local_eventcalendar');
            default:
                return get_string('testemailfailed', 'local_eventcalendar');
        }
    }

    /**
     * Sends the notification of a day, as a test, to a single user.
     *
     * The real message is sent through the same provider (db/messages.php),
     * so it is delivered as a subscriber would receive it. Nothing is
     * recorded as sent, and subscription, visibility, access rules and time
     * are ignored.
     *
     * @param calendar $calendar
     * @param day $day May be a copy with unsaved values.
     * @param \stdClass $user Recipient (the current user).
     * @return string One of the TEST_* constants.
     */
    public static function send_test_notification(calendar $calendar, day $day, \stdClass $user): string {
        $content = self::build_content($calendar, $day, $user, \context_system::instance());

        if ($content === null) {
            return self::TEST_EMPTY;
        }

        return message_send(self::build_message($calendar, $day, $user, $content)) ? self::TEST_SENT : self::TEST_FAILED;
    }

    /**
     * Returns the message, in the current language, for the result of a test notification.
     *
     * @param string $result One of the TEST_* constants.
     * @return string
     */
    public static function get_test_notification_message(string $result): string {
        switch ($result) {
            case self::TEST_SENT:
                return get_string('testnotificationsent', 'local_eventcalendar');
            case self::TEST_EMPTY:
                return get_string('testnothingtosend', 'local_eventcalendar');
            default:
                return get_string('testnotificationfailed', 'local_eventcalendar');
        }
    }
}
