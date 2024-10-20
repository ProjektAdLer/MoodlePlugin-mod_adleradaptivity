<?php

namespace mod_adleradaptivity\local\db;

use moodle_database;

abstract class base_repository {
    public function __construct(
        protected readonly moodle_database $db
    ) {}
}