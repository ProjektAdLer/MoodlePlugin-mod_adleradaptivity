<?php

namespace mod_adleradaptivity\local\db;

use dml_exception;
use moodle_database;
use stdClass;

class adleradaptivity_attempt_repository {
    private moodle_database $db;

    public function __construct($db = null) {
        if (is_null($db)) {
            global $DB;
            $this->db = $DB;
        } else {
            $this->db = $db;
        }
    }

    /**
     * Get adleradaptivity attempt by moodle attempt / quba id
     *
     * @param int $attempt_id "moodle attempt" / quba ({@see question_usage_by_activity}) id
     * @return stdClass adleradaptivity_attempt db object
     * @throws dml_exception
     */
    public function get_adleradaptivity_attempt_by_quba_id(int $attempt_id): stdClass {
        return $this->db->get_record('adleradaptivity_attempts', ['attempt_id' => $attempt_id], '*', MUST_EXIST);
    }

    /**
     * Create adleradaptivity_attempt in database
     *
     * @param stdClass $adleradaptivity_attempt
     * @return int id of the created adleradaptivity_attempt
     * @throws dml_exception
     */
    public function create_adleradaptivity_attempt(stdClass $adleradaptivity_attempt): int {
        return $this->db->insert_record('adleradaptivity_attempts', $adleradaptivity_attempt);
    }
}