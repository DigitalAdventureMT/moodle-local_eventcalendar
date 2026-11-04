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

namespace local_eventcalendar;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\accessrule\registry;
use local_eventcalendar\local\notification_dispatcher;
use local_eventcalendar\local\subscription;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\notification_dispatcher::class)]
/**
 * Tests for the daily notification dispatch: time window in the user timezone, single send,
 * subscription, visibility, access rules and test sends.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\notification_dispatcher
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_dispatcher_test extends \advanced_testcase {
    /** @var int Today's midnight in UTC. */
    protected $today;

    /** @var \local_eventcalendar\persistent\calendar */
    protected $calendar;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        access_rules::reset_cache();
        registry::reset();

        $this->today = \local_eventcalendar_generator::utc_midnight(time());
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $this->calendar = $generator->create_calendar(['startdate' => $this->today - 5 * DAYSECS]);
        $generator->create_day(['calendarid' => $this->calendar->get('id'), 'opendate' => $this->today, 'notiftitle' => 'Today']);
    }

    /**
     * Returns a UTC user enrolled ten days ago.
     *
     * @param array $record
     * @return \stdClass
     */
    protected function subscribed_user(array $record = []): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user(array_merge(['timezone' => 'UTC'], $record));
        subscription::subscribe((int) $this->calendar->get('id'), (int) $user->id);
        $DB->set_field('local_eventcalendar_subscr', 'timesubscribed', $this->today - 10 * DAYSECS, ['userid' => $user->id]);

        return $user;
    }

    /**
     * Runs the dispatch at the given instant and returns the messages.
     *
     * @param int $now
     * @return array
     */
    protected function dispatch(int $now): array {
        $sink = $this->redirectMessages();
        notification_dispatcher::dispatch($now);
        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    public function test_sent_once_in_the_morning_window(): void {
        $user = $this->subscribed_user();

        $this->assertCount(0, $this->dispatch($this->today + 7 * HOURSECS), 'Before 8');

        $messages = $this->dispatch($this->today + 9 * HOURSECS);
        $this->assertCount(1, $messages);
        $this->assertSame('Today', $messages[0]->subject);
        $this->assertEquals($user->id, $messages[0]->useridto);

        $this->assertCount(0, $this->dispatch($this->today + 10 * HOURSECS), 'Already sent');
    }

    public function test_user_timezone(): void {
        // At 9 UTC it is the middle of the night in Los Angeles, so the notification is not sent.
        $this->subscribed_user(['timezone' => 'America/Los_Angeles']);

        $this->assertCount(0, $this->dispatch($this->today + 9 * HOURSECS));
        $this->assertCount(1, $this->dispatch($this->today + 17 * HOURSECS));
    }

    public function test_not_sent_after_the_day_ended(): void {
        $this->subscribed_user();

        $this->assertCount(0, $this->dispatch($this->today + DAYSECS + HOURSECS));
    }

    public function test_late_subscription_misses_the_day(): void {
        global $DB;

        $user = $this->subscribed_user();
        $DB->set_field('local_eventcalendar_subscr', 'timesubscribed', $this->today + 9 * HOURSECS, ['userid' => $user->id]);

        $this->assertCount(0, $this->dispatch($this->today + 10 * HOURSECS));
    }

    public function test_hidden_calendar_or_access_rules_block_sending(): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->subscribed_user();
        $outsider = $this->subscribed_user();
        cohort_add_member($cohort->id, $member->id);

        access_rules::save((int) $this->calendar->get('id'), [
            ['rules' => [['ruletype' => 'cohort', 'subject' => (string) $cohort->id, 'comparison' => 'is', 'value' => '']]],
        ]);

        $messages = $this->dispatch($this->today + 9 * HOURSECS);
        $this->assertCount(1, $messages);
        $this->assertEquals($member->id, $messages[0]->useridto);
        $this->assertNotEquals($outsider->id, $messages[0]->useridto);
    }

    public function test_hidden_calendar_blocks_sending(): void {
        $this->subscribed_user();
        $this->calendar->set('forcehide', 1);
        $this->calendar->update();

        $this->assertCount(0, $this->dispatch($this->today + 9 * HOURSECS));
    }

    public function test_test_email_and_notification(): void {
        $user = $this->getDataGenerator()->create_user();
        $days = \local_eventcalendar\persistent\day::get_records(['calendarid' => $this->calendar->get('id')]);
        $day = reset($days);

        $emailsink = $this->redirectEmails();
        $this->assertSame(
            notification_dispatcher::TEST_SENT,
            notification_dispatcher::send_test_email($this->calendar, $day, $user)
        );
        $this->assertCount(1, $emailsink->get_messages());
        $emailsink->close();

        $messagesink = $this->redirectMessages();
        $this->assertSame(
            notification_dispatcher::TEST_SENT,
            notification_dispatcher::send_test_notification($this->calendar, $day, $user)
        );
        $this->assertCount(1, $messagesink->get_messages());
        $messagesink->close();

        $day->set('notiftitle', '');
        $day->set('notifbody', '');
        $this->assertSame(
            notification_dispatcher::TEST_EMPTY,
            notification_dispatcher::send_test_email($this->calendar, $day, $user)
        );
    }

    /**
     * The images of the plugin and of its subplugins in the email get the token of the recipient, in both
     * address forms of Moodle; the site logo, files of other plugins and external images are left as they are.
     */
    public function test_add_file_tokens(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $contextid = \context_system::instance()->id;
        $token = get_user_key('core_files', $user->id);

        $own = \moodle_url::make_pluginfile_url($contextid, 'local_eventcalendar', 'notificationmessage', 7, '/', 'a b.png');
        $addon = \moodle_url::make_pluginfile_url($contextid, 'ecaddon_sample', 'logo', 3, '/', 'logo.png');
        $pack = \moodle_url::make_pluginfile_url($contextid, 'ecpack_sample', 'icons', 0, '/', 'day1.png');
        $other = \moodle_url::make_pluginfile_url($contextid, 'mod_page', 'content', 0, '/', 'other.png');
        $core = \moodle_url::make_pluginfile_url($contextid, 'core_admin', 'logo', 0, '/0x150/', 'site.png');
        $html = '<img src="' . $own->out() . '"><img src="' . $addon->out() . '"><img src="' . $core->out() . '">'
            . '<img src="' . $pack->out() . '"><img src="' . $other->out() . '">'
            . '<img src="https://example.com/pluginfile.php/' . $contextid . '/local_eventcalendar/content/1/x.png">';

        $result = notification_dispatcher::add_file_tokens($html, (int) $user->id);

        $tokenbase = $CFG->wwwroot . '/tokenpluginfile.php/' . $token . '/' . $contextid;
        $this->assertStringContainsString('"' . $tokenbase . '/local_eventcalendar/notificationmessage/7/a%20b.png"', $result);
        $this->assertStringContainsString('"' . $tokenbase . '/ecaddon_sample/logo/3/logo.png"', $result);
        $this->assertStringContainsString('"' . $tokenbase . '/ecpack_sample/icons/0/day1.png"', $result);
        $this->assertStringContainsString('"' . $core->out() . '"', $result);
        $this->assertStringContainsString('"' . $other->out() . '"', $result);
        $this->assertStringContainsString('"https://example.com/pluginfile.php/', $result);

        // Without slash arguments the file is a parameter, and the token another one.
        $CFG->slasharguments = 0;
        $own = \moodle_url::make_pluginfile_url($contextid, 'local_eventcalendar', 'content', 4, '/', 'p.png');
        $result = notification_dispatcher::add_file_tokens('<img src="' . $own->out() . '">', (int) $user->id);
        $this->assertStringContainsString('/tokenpluginfile.php?file=', $result);
        $this->assertStringContainsString('token=' . $token, $result);
    }

    /**
     * A sent email carries the token in the addresses of the images of the notification body.
     */
    public function test_sent_email_images_have_token(): void {
        $user = $this->getDataGenerator()->create_user();
        $days = \local_eventcalendar\persistent\day::get_records(['calendarid' => $this->calendar->get('id')]);
        $day = reset($days);
        $image = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'local_eventcalendar',
            'notificationmessage', 9, '/', 'pic.png');
        $day->set('notifbody', '<p>Hello</p><img src="' . $image->out() . '" alt="">');

        $sink = $this->redirectEmails();
        notification_dispatcher::send_test_email($this->calendar, $day, $user);
        $messages = $sink->get_messages();
        $sink->close();

        $body = quoted_printable_decode($messages[0]->body);
        $this->assertStringContainsString('/tokenpluginfile.php/' . get_user_key('core_files', $user->id) . '/', $body);
        $this->assertStringNotContainsString('/pluginfile.php/', $body);
    }

    public function test_limit_per_run(): void {
        set_config('maxemailsperrun', 2, 'local_eventcalendar');
        $this->subscribed_user();
        $this->subscribed_user();
        $this->subscribed_user();

        $this->assertCount(2, $this->dispatch($this->today + 9 * HOURSECS));
        $this->assertCount(1, $this->dispatch($this->today + 9 * HOURSECS + 600));
        $this->assertCount(0, $this->dispatch($this->today + 9 * HOURSECS + 1200));
    }

    public function test_no_limit(): void {
        set_config('maxemailsperrun', 0, 'local_eventcalendar');
        $this->subscribed_user();
        $this->subscribed_user();

        $this->assertCount(2, $this->dispatch($this->today + 9 * HOURSECS));
    }

    public function test_earliest_send_time_first(): void {
        set_config('maxemailsperrun', 1, 'local_eventcalendar');
        $this->subscribed_user();
        // Tokyo is ahead of UTC: its 8:00 comes earlier, so its notification is due since longer.
        $tokyo = $this->subscribed_user(['timezone' => 'Asia/Tokyo']);

        // At 14 UTC it is 23 in Tokyo, still the same local day.
        $messages = $this->dispatch($this->today + 14 * HOURSECS);
        $this->assertCount(1, $messages);
        $this->assertEquals($tokyo->id, $messages[0]->useridto);
    }

    public function test_invalid_email_is_skipped(): void {
        set_config('maxemailsperrun', 1, 'local_eventcalendar');
        $invalid = $this->subscribed_user(['email' => 'not-an-email']);
        $valid = $this->subscribed_user();

        // The invalid address does not use the limit and is recorded, so it is written in the log only once.
        $this->expectOutputString('local_eventcalendar: notification not sent, invalid email address of user '
            . $invalid->id . "\n");
        $messages = $this->dispatch($this->today + 9 * HOURSECS);
        $this->assertCount(1, $messages);
        $this->assertEquals($valid->id, $messages[0]->useridto);
        $this->assertCount(0, $this->dispatch($this->today + 10 * HOURSECS));
    }

    public function test_unsubscribe_headers(): void {
        $user = $this->subscribed_user();

        $sink = $this->redirectEmails();
        notification_dispatcher::dispatch($this->today + 9 * HOURSECS);
        $emails = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $emails);
        $url = \local_eventcalendar\local\unsubscribe::get_url((int) $this->calendar->get('id'), (int) $user->id)->out(false);
        $this->assertStringContainsString('List-Unsubscribe: <' . $url . '>', $emails[0]->header);
        $this->assertStringContainsString('List-Unsubscribe-Post: List-Unsubscribe=One-Click', $emails[0]->header);
    }

    public function test_suspended_user_and_calendar_without_days(): void {
        $this->subscribed_user(['suspended' => 1]);
        $active = $this->subscribed_user();

        // A calendar without days near now does not take part in the run.
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $other = $generator->create_calendar(['startdate' => $this->today - 50 * DAYSECS]);
        $generator->create_day(['calendarid' => $other->get('id'), 'opendate' => $this->today - 40 * DAYSECS]);
        subscription::subscribe((int) $other->get('id'), (int) $active->id);

        $messages = $this->dispatch($this->today + 9 * HOURSECS);
        $this->assertCount(1, $messages);
        $this->assertEquals($active->id, $messages[0]->useridto);
    }

    /**
     * Returns a dispatcher whose sending fails the given number of times, then succeeds.
     *
     * @param int $failures
     * @return notification_dispatcher
     */
    protected function failing_dispatcher(int $failures): notification_dispatcher {
        $dispatcher = new class extends notification_dispatcher {
            /** @var int Failures still to simulate. */
            public static $failures = 0;

            /** @var int Calls to send_message(). */
            public static $calls = 0;

            /**
             * Simulates the sending.
             *
             * @param \core\message\message $message
             * @return bool
             */
            protected static function send_message(\core\message\message $message): bool {
                self::$calls++;
                if (self::$failures > 0) {
                    self::$failures--;
                    return false;
                }
                return true;
            }
        };
        $dispatcher::$failures = $failures;
        $dispatcher::$calls = 0;

        return $dispatcher;
    }

    public function test_failed_sending_is_retried_once(): void {
        global $DB;

        $user = $this->subscribed_user();
        $dispatcher = $this->failing_dispatcher(5);
        $this->expectOutputRegex('/retried by the next run.*\n.*after 2 attempts/');

        $this->assertSame(0, $dispatcher::dispatch($this->today + 9 * HOURSECS));
        $record = $DB->get_record('local_eventcalendar_sent', ['userid' => $user->id]);
        $this->assertNull($record->timesent);
        $this->assertEquals(1, $record->attempts);

        $this->assertSame(0, $dispatcher::dispatch($this->today + 9 * HOURSECS + 600));
        $this->assertEquals(2, $DB->get_field('local_eventcalendar_sent', 'attempts', ['userid' => $user->id]));

        // Given up: no further attempts.
        $dispatcher::dispatch($this->today + 9 * HOURSECS + 1200);
        $this->assertSame(2, $dispatcher::$calls);
    }

    public function test_retry_succeeds(): void {
        global $DB;

        $user = $this->subscribed_user();
        $dispatcher = $this->failing_dispatcher(1);
        $this->expectOutputRegex('/retried by the next run/');

        $this->assertSame(0, $dispatcher::dispatch($this->today + 9 * HOURSECS));
        $this->assertSame(1, $dispatcher::dispatch($this->today + 9 * HOURSECS + 600));

        $record = $DB->get_record('local_eventcalendar_sent', ['userid' => $user->id]);
        $this->assertEquals($this->today + 9 * HOURSECS + 600, $record->timesent);
        $this->assertEquals(2, $record->attempts);
        $this->assertSame(0, $dispatcher::dispatch($this->today + 9 * HOURSECS + 1200));
        $this->assertSame(2, $dispatcher::$calls);
    }

    public function test_skipped_invalid_email_is_recorded(): void {
        global $DB;

        $user = $this->subscribed_user(['email' => 'not-an-email']);
        $this->expectOutputRegex('/invalid email address/');
        $this->dispatch($this->today + 9 * HOURSECS);

        $record = $DB->get_record('local_eventcalendar_sent', ['userid' => $user->id]);
        $this->assertNull($record->timesent);
        $this->assertEquals(0, $record->attempts);
    }
}
