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

namespace mod_adleradaptivity\navigation\views;

use core\navigation\views\secondary as core_secondary;

/**
 * Class secondary_navigation_view.
 *
 * Custom implementation for a plugin.
 *
 * @package     mod_adleradaptivity
 * @category    navigation
 * @copyright   2023 Markus Heck
 */
//class secondary extends core_secondary {
//    /**
//     * Define a custom secondary nav order/view.
//     *
//     * @return array
//     */
//    protected function get_default_module_mapping(): array {
//        $defaultmaping = parent::get_default_module_mapping();
//
//        $defaultmaping[self::TYPE_SETTING] = array_merge($defaultmaping[self::TYPE_SETTING], [
//            'modedit' => 100,
//            "mod_{$this->page->activityname}_useroverrides" => 3, // Overrides are module specific.
//            "mod_{$this->page->activityname}_groupoverrides" => 4,
//            'roleassign' => 7.2,
//            'filtermanage' => 6,
//            'roleoverride' => 7,
//            'rolecheck' => 7.1,
//            'logreport' => 8,
//            'backup' => 9,
//            'restore' => 10,
//            'competencybreakdown' => 11,
//            'sendtomoodlenet' => 16,
//        ]);
//
//        $defaultmaping[self::TYPE_CONTAINER] = [
//            'questionbank' => 5,
//        ];
//
//        return $defaultmaping;
//    }
//}
