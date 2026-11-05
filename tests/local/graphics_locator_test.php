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

namespace local_eventcalendar\local;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\graphics_locator::class)]
/**
 * Tests of the locator of the static graphic files.
 *
 * Only the files shipped with the plugin are available in the test environment: the tests check
 * the behaviour of the locator on the plugin root, which is the only one guaranteed to exist.
 * The file names used here (for example Banner01.png) must match the files of pix/ exactly, because
 * the file system of the test servers is case and name sensitive.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\graphics_locator
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class graphics_locator_test extends \advanced_testcase {
    /**
     * Roots are cached in memory: start each test from a clean state.
     */
    protected function setUp(): void {
        parent::setUp();
        graphics_locator::reset_caches();
    }

    /**
     * The plugin is always the first root, for every type of graphics.
     */
    public function test_plugin_is_the_first_root(): void {
        foreach ([graphics_locator::ICONSETS, graphics_locator::BANNERS, graphics_locator::HEADERFOOTERS] as $type) {
            $roots = graphics_locator::get_roots($type);

            $this->assertNotEmpty($roots, $type);
            $this->assertSame('local_eventcalendar', $roots[0]['component'], $type);
            $this->assertDirectoryExists($roots[0]['dir'], $type);
        }
    }

    /**
     * A file shipped with the plugin is found, a missing one is not.
     */
    public function test_find_file(): void {
        $path = graphics_locator::find_file(graphics_locator::BANNERS, 'Banner01.png');
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        $this->assertNull(graphics_locator::find_file(graphics_locator::BANNERS, 'missing.png'));
    }

    /**
     * An icon set is found as a directory, and its files as files.
     */
    public function test_find_directory_and_file_of_an_iconset(): void {
        $this->assertNotNull(graphics_locator::find_directory(graphics_locator::ICONSETS, 'advent-1'));
        $this->assertNotNull(graphics_locator::find_file(graphics_locator::ICONSETS, 'advent-1/01.png'));

        // A directory is not a file and the other way round.
        $this->assertNull(graphics_locator::find_file(graphics_locator::ICONSETS, 'advent-1'));
        $this->assertNull(graphics_locator::find_directory(graphics_locator::ICONSETS, 'advent-1/01.png'));
    }

    /**
     * Paths pointing outside the graphics folders are never resolved.
     */
    public function test_invalid_paths_are_rejected(): void {
        foreach (['', '/etc/passwd', '../version.php', '../../version.php', 'advent-1/../../../version.php', 'a\\b'] as $path) {
            $this->assertNull(graphics_locator::find_file(graphics_locator::BANNERS, $path), $path);
            $this->assertNull(graphics_locator::find_directory(graphics_locator::ICONSETS, $path), $path);
        }
    }

    /**
     * The URL of a file of the plugin is the same one used before the introduction of the packs.
     */
    public function test_url_of_a_plugin_file(): void {
        $url = graphics_locator::get_url(graphics_locator::BANNERS, 'Banner01.png');

        $this->assertSame('/local/eventcalendar/pix/banners/Banner01.png', $url->out_as_local_url(false));
    }

    /**
     * A file that cannot be found still gets a URL, without exceptions.
     */
    public function test_url_of_a_missing_file(): void {
        $url = graphics_locator::get_url(graphics_locator::HEADERFOOTERS, 'missing.png');

        $this->assertSame('/local/eventcalendar/pix/headerfooters/missing.png', $url->out_as_local_url(false));
    }

    /**
     * An unknown type of graphics is a programming error.
     */
    public function test_unknown_type_throws_exception(): void {
        $this->expectException(\coding_exception::class);

        graphics_locator::get_roots('unknown');
    }
}
