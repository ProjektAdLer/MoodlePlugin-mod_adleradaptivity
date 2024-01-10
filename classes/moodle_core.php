<?php

namespace mod_adleradaptivity;

use dml_exception;

/**
 * This class contains aliases for moodle core functions to allow mocking them.
 */
class moodle_core {
    /**
     * @throws dml_exception
     */
    public static function get_course($courseid, $clone = true): \stdClass {
        return get_course($courseid, $clone);
    }
}