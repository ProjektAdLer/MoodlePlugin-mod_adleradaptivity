<?php

namespace mod_adleradaptivity\local\db;

use moodle_database;

abstract class base_repository {
    protected moodle_database $db;

    public function __construct(moodle_database|null $db = null) {
        if (is_null($db)) {
            global $DB;
            $this->db = $DB;
        } else {
            $this->db = $db;
        }
    }
}