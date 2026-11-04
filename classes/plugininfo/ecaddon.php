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
 * Plugin info for the ecaddon subplugin type.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\plugininfo;

use core\plugininfo\base;

/**
 * Plugin info for the ecaddon subplugin type.
 *
 * An ecaddon plugin extends the calendar through the hooks defined in
 * local_eventcalendar\hook. This class only registers the type with the plugin
 * manager: addons have no enable/disable switch, they are active as soon as
 * they are installed, and they are removed with the standard uninstall page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ecaddon extends base {
    /**
     * Addons can be uninstalled from the administration interface.
     *
     * By default Moodle forbids it, so it has to be allowed explicitly. The
     * data created by an addon is removed by the addon itself (db/uninstall.php)
     * before Moodle drops its tables and files.
     *
     * @return bool
     */
    #[\Override]
    public function is_uninstall_allowed(): bool {
        return true;
    }

    /**
     * Warning shown on the uninstall confirmation page.
     *
     * The text is generic on purpose: the base plugin does not know what the
     * installed addon stores.
     *
     * @return string
     */
    #[\Override]
    public function get_uninstall_extra_warning(): string {
        return get_string('ecaddonuninstallwarning', 'local_eventcalendar');
    }
}
