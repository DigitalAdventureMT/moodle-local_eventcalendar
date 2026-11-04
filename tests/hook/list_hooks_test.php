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

namespace local_eventcalendar\hook;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\calendar_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\notificationset_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\iconset_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\emailtemplate_list::class)]
/**
 * Tests of the hooks that extend the list pages of the plugin.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\hook\calendar_list
 * @covers      \local_eventcalendar\hook\notificationset_list
 * @covers      \local_eventcalendar\hook\iconset_list
 * @covers      \local_eventcalendar\hook\emailtemplate_list
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_hooks_test extends \advanced_testcase {
    /**
     * List hooks with the name of the method that returns their items.
     *
     * @return array
     */
    public static function list_hook_provider(): array {
        return [
            'calendars' => [calendar_list::class, 'get_calendars'],
            'notification sets' => [notificationset_list::class, 'get_sets'],
            'icon sets' => [iconset_list::class, 'get_iconsets'],
            'email templates' => [emailtemplate_list::class, 'get_templates'],
        ];
    }

    /**
     * Each hook returns the items it was created with, and the row actions are kept by item, in order.
     *
     * @dataProvider list_hook_provider
     * @param string $class
     * @param string $getter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('list_hook_provider')]
    public function test_items_and_row_actions(string $class, string $getter): void {
        $items = [new \stdClass(), new \stdClass()];
        $hook = new $class($items);

        $this->assertSame($items, $hook->$getter());
        $this->assertSame([], $hook->get_row_actions(5));

        $hook->add_row_action(5, '<a>one</a>');
        $hook->add_row_action(5, '<a>two</a>');
        $hook->add_row_action(6, '<a>other</a>');

        $this->assertSame(['<a>one</a>', '<a>two</a>'], $hook->get_row_actions(5));
        $this->assertSame(['<a>other</a>'], $hook->get_row_actions(6));
    }

    /**
     * The hooks of the notification sets and of the icon sets accept toolbar buttons.
     */
    public function test_toolbar_buttons(): void {
        foreach ([new notificationset_list([]), new iconset_list([])] as $hook) {
            $this->assertSame([], $hook->get_toolbar_buttons());

            $hook->add_toolbar_button('<a>first</a>');
            $hook->add_toolbar_button('<a>second</a>');

            $this->assertSame(['<a>first</a>', '<a>second</a>'], $hook->get_toolbar_buttons());
        }
    }

    /**
     * The hook of the calendars accepts blocks of HTML under the table.
     */
    public function test_calendar_footers(): void {
        $hook = new calendar_list([]);
        $this->assertSame([], $hook->get_footers());

        $hook->add_footer('<div>one</div>');
        $hook->add_footer('<div>two</div>');

        $this->assertSame(['<div>one</div>', '<div>two</div>'], $hook->get_footers());
    }
}
