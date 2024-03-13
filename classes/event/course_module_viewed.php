<?php

/**
 * The mod_adleradaptivity course module viewed event.
 */

namespace mod_adleradaptivity\event;

use core\event\course_module_viewed as core_course_module_viewed;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_adleradaptivity course module viewed event class.
 */
class course_module_viewed extends core_course_module_viewed {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'adleradaptivity';
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'adleradaptivity', 'restore' => 'adleradaptivity'];
    }
}

// TODO: documentation