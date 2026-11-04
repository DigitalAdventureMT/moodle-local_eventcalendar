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
 * Visual elements of the calendar reports page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use html_writer;

/**
 * Visual elements of the calendar reports page (report.php): the progress bar of the unlocked days.
 *
 * It uses only Bootstrap classes with the same name in Bootstrap 4 and 5, so it looks the same in every
 * supported Moodle version and needs no JavaScript. The methods are public so that other plugins showing
 * calendar figures can use the same elements.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view {
    /**
     * Text "part of total".
     *
     * @param int $part
     * @param int $total
     * @return string
     */
    public static function part_of(int $part, int $total): string {
        return get_string('reportpartof', 'local_eventcalendar', (object) ['part' => $part, 'total' => $total]);
    }

    /**
     * Progress bar with the text "done of total" next to it.
     *
     * The text is visible and is also the accessible name of the bar, so that the value is not given
     * by the colour alone.
     *
     * @param int $done
     * @param int $total
     * @return string
     */
    public static function progress(int $done, int $total): string {
        $text = self::part_of($done, $total);
        $width = $total > 0 ? round(100 * $done / $total) : 0;

        $bar = html_writer::div(
            html_writer::div('', 'progress-bar', [
                'role' => 'progressbar',
                'style' => 'width: ' . $width . '%;',
                'aria-valuenow' => $done,
                'aria-valuemin' => 0,
                'aria-valuemax' => $total,
                'aria-label' => $text,
            ]),
            'progress flex-grow-1'
        );

        return html_writer::div(
            $bar . html_writer::span($text, 'text-nowrap'),
            'd-flex align-items-center local-eventcalendar-gap-sm local-eventcalendar-progress'
        );
    }
}
