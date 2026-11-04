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
 * Locator of the static graphic files (icon sets, banners, header/footer pairs).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Locator of the static graphic files (icon sets, banners, header/footer pairs).
 *
 * The standard graphics are static files shipped in the pix folder of the plugin:
 *
 *   pix/iconsets/{identifier}/...
 *   pix/banners/...
 *   pix/headerfooters/...
 *
 * Graphics packs (subplugins of type ecpack) can ship the same structure inside their own pix folder.
 * This class is the only place that knows where those folders are: scanners, persistent classes and the
 * exporter ask the locator instead of building paths from the plugin directory.
 *
 * Search order: the plugin itself first, then the packs sorted by name. A file is always taken from the
 * first root that contains it, so identifiers must be unique across roots (the scanners ignore the
 * duplicates found in later roots).
 *
 * Paths are obtained from core_component and never from a fixed concatenation with dirroot, because
 * the location of plugins changes with the directory structure of Moodle 5.1 and later.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graphics_locator {
    /** Type of graphics: icon sets (one folder per set). */
    public const ICONSETS = 'iconsets';

    /** Type of graphics: banners. */
    public const BANNERS = 'banners';

    /** Type of graphics: header and footer pairs. */
    public const HEADERFOOTERS = 'headerfooters';

    /** Component of the plugin, always the first root searched. */
    private const COMPONENT = 'local_eventcalendar';

    /** @var array[] Roots already computed in this request, by type. */
    private static array $roots = [];

    /**
     * Returns the folders that may contain graphics of a given type, in search order.
     *
     * Only existing folders are returned. Each item has the keys 'component' (frankenstyle name of the
     * plugin that owns the folder) and 'dir' (absolute path of the folder, without trailing slash).
     *
     * @param string $type One of the type constants of this class.
     * @return array[]
     */
    public static function get_roots(string $type): array {
        self::validate_type($type);

        if (isset(self::$roots[$type])) {
            return self::$roots[$type];
        }

        // The plugin comes first, then the packs in alphabetical order, so that the result does not
        // depend on the order in which the file system lists the folders.
        $candidates = [self::COMPONENT => \core_component::get_component_directory(self::COMPONENT)];

        $packs = \core_component::get_plugin_list('ecpack');
        ksort($packs);
        foreach ($packs as $name => $packdir) {
            $candidates['ecpack_' . $name] = $packdir;
        }

        $roots = [];
        foreach ($candidates as $component => $plugindir) {
            if (empty($plugindir)) {
                continue;
            }

            $dir = $plugindir . '/pix/' . $type;
            if (is_dir($dir)) {
                $roots[] = ['component' => $component, 'dir' => $dir];
            }
        }

        self::$roots[$type] = $roots;

        return $roots;
    }

    /**
     * Returns the absolute path of a file, searching the roots in order.
     *
     * @param string $type One of the type constants of this class.
     * @param string $relativepath Path relative to the type folder, for example 'advent-1/01.png'.
     * @return string|null Null if the path is not valid or the file does not exist in any root.
     */
    public static function find_file(string $type, string $relativepath): ?string {
        $found = self::locate($type, $relativepath, false);

        return $found === null ? null : $found['path'];
    }

    /**
     * Returns the absolute path of a directory, searching the roots in order.
     *
     * Used for icon sets, where the identifier is the name of the folder.
     *
     * @param string $type One of the type constants of this class.
     * @param string $relativepath Path relative to the type folder, for example 'advent-1'.
     * @return string|null Null if the path is not valid or the directory does not exist in any root.
     */
    public static function find_directory(string $type, string $relativepath): ?string {
        $found = self::locate($type, $relativepath, true);

        return $found === null ? null : $found['path'];
    }

    /**
     * Returns the URL of a static file.
     *
     * For files of the plugin the URL is the same one used before the introduction of the packs. If the file
     * is not found in any root, the URL of the plugin location is returned anyway, so that callers always get a
     * URL (a missing file results in a broken image, not in an exception).
     *
     * @param string $type One of the type constants of this class.
     * @param string $relativepath Path relative to the type folder.
     * @return \moodle_url
     */
    public static function get_url(string $type, string $relativepath): \moodle_url {
        $found = self::locate($type, $relativepath, false);

        if ($found !== null && $found['component'] !== self::COMPONENT) {
            $urlpath = self::path_to_url_path($found['path']);

            if ($urlpath !== null) {
                return new \moodle_url($urlpath);
            }
        }

        return new \moodle_url('/local/eventcalendar/pix/' . $type . '/' . $relativepath);
    }

    /**
     * Clears the roots kept in memory. Used by the unit tests.
     */
    public static function reset_caches(): void {
        self::$roots = [];
    }

    /**
     * Looks for a relative path in the roots, in order.
     *
     * @param string $type One of the type constants of this class.
     * @param string $relativepath Path relative to the type folder.
     * @param bool $directory True to look for a directory, false for a file.
     * @return array|null ['component' => ..., 'path' => ...] of the first match, null if none.
     */
    private static function locate(string $type, string $relativepath, bool $directory): ?array {
        // The path comes from the database or from a folder scan, but it is checked anyway: the locator
        // must never return a path outside the graphics folders.
        if (
            $relativepath === ''
            || $relativepath[0] === '/'
            || str_contains($relativepath, '..')
            || str_contains($relativepath, '\\')
            || str_contains($relativepath, "\0")
        ) {
            return null;
        }

        foreach (self::get_roots($type) as $root) {
            $path = $root['dir'] . '/' . $relativepath;

            if ($directory ? is_dir($path) : is_file($path)) {
                return ['component' => $root['component'], 'path' => $path];
            }
        }

        return null;
    }

    /**
     * Converts the absolute path of a file of a pack into a path relative to the web root.
     *
     * Before Moodle 5.1 the web root is dirroot. From 5.1 the code is in the "public" folder, which is
     * the web root: depending on how dirroot is set, the "public" segment may need to be removed.
     *
     * @param string $path Absolute path of the file.
     * @return string|null Path starting with a slash, null if the file is outside dirroot.
     */
    private static function path_to_url_path(string $path): ?string {
        global $CFG;

        $base = rtrim($CFG->dirroot, '/');

        if (!str_starts_with($path, $base . '/')) {
            return null;
        }

        $urlpath = substr($path, strlen($base));

        if (!str_ends_with($base, '/public') && str_starts_with($urlpath, '/public/')) {
            $urlpath = substr($urlpath, strlen('/public'));
        }

        return $urlpath;
    }

    /**
     * Checks that the type of graphics is one of the supported ones.
     *
     * @param string $type
     * @throws \coding_exception
     */
    private static function validate_type(string $type): void {
        if (!in_array($type, [self::ICONSETS, self::BANNERS, self::HEADERFOOTERS], true)) {
            throw new \coding_exception('Unknown type of graphics: ' . $type);
        }
    }
}
