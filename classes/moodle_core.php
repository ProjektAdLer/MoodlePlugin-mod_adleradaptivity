<?php

namespace mod_adleradaptivity;

use coding_exception;
use dml_exception;
use moodle_exception;
use stdClass;

/**
 * This class contains aliases for moodle core functions to allow mocking them.
 */
class moodle_core {
    /**
     * @throws dml_exception
     */
    public static function get_course(int|string $course_id, $clone = true): stdClass {
        return get_course($course_id, $clone);
    }

    /**
     * @throws coding_exception
     */
    public static function get_coursemodule_from_id(...$args): bool|stdClass {
        return get_coursemodule_from_id(...$args);
    }

    /**
     * @throws moodle_exception
     */
    public static function redirect(...$args): void {
        redirect(...$args);
    }
}