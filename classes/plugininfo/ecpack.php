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
 * Plugin info for the ecpack subplugin type.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\plugininfo;

use core\plugininfo\base;

/**
 * Plugin info for the ecpack subplugin type.
 *
 * An ecpack plugin ships graphic elements (icon sets, banners, header and
 * footer pairs) in the same folder structure used by the base plugin under pix/.
 * This class only registers the type with the plugin manager. Packs have no
 * enable/disable switch and can be removed with the standard uninstall page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ecpack extends base {
    /**
     * Packs can be uninstalled from the administration interface.
     *
     * @return bool
     */
    #[\Override]
    public function is_uninstall_allowed(): bool {
        return true;
    }
}
