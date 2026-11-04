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
 * Date resolution strategy for holidays computed by an algorithm.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\date_resolver;

/**
 * Date resolution strategy for holidays computed by an algorithm.
 *
 * The only algorithm so far is the Gregorian Easter (Meeus/Jones/Butcher method) in plain PHP, avoiding the
 * optional PHP "calendar" extension (easter_date()), which is not available on every host. The $algorithm
 * constructor parameter leaves room for other algorithms.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class computed implements date_resolver_interface {
    /** @var string key of the algorithm to apply */
    private string $algorithm;

    /**
     * Constructor.
     *
     * @param string $algorithm algorithm key, for example 'easter'
     */
    public function __construct(string $algorithm = 'easter') {
        $this->algorithm = $algorithm;
    }

    /**
     * Reference date of the event for the given year.
     *
     * @param int $year calendar year
     * @return int Unix timestamp (midnight UTC)
     */
    public function resolve(int $year): int {
        switch ($this->algorithm) {
            case 'easter':
                [$month, $day] = $this->calculate_easter($year);
                // Explicit 'UTC' timezone, for the same reason explained in fixed.php.
                return make_timestamp($year, $month, $day, 0, 0, 0, 'UTC');

            default:
                throw new \moodle_exception(
                    'errorunknownalgorithm',
                    'local_eventcalendar',
                    '',
                    $this->algorithm
                );
        }
    }

    /**
     * Computes month and day of Gregorian Easter for a given year.
     *
     * Meeus/Jones/Butcher algorithm. It returns a [month, day] pair rather than a timestamp, so resolve()
     * alone applies make_timestamp() with the right timezone.
     *
     * @param int $year calendar year
     * @return array{0: int, 1: int} month and day of Easter Sunday
     */
    private function calculate_easter(int $year): array {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return [$month, $day];
    }
}
