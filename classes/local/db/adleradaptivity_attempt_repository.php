<?php

namespace mod_adleradaptivity\local\db;

use context_module;
use dml_exception;
use dml_missing_record_exception;
use stdClass;

class adleradaptivity_attempt_repository extends base_repository {
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
     * @throws dml_exception
     */
    public function delete_adleradaptivity_attempt_by_question_usage_id(int $question_usage_id): bool {
        return $this->db->delete_records('adleradaptivity_attempts', ['attempt_id' => $question_usage_id]);
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

    /** Load adleradaptivity_attempts by cmid
     * - Get context by cmid
     * - with the context id get the question_usage
     * - question_usage and adleradaptivity_attempts are a 1:1 relation
     *
     * @param int $cmid The course module ID of the adleradaptivity element.
     * @return array adleradaptivity_attempt The question usage object.
     * @throws dml_exception
     * @throws dml_missing_record_exception If the expected records are not found.
     */
    public function get_adleradaptivity_attempt_by_cmid(int $cmid): array {
        // Get the context for the provided course module ID.
        $modulecontext = context_module::instance($cmid);

        // Create SQL to join adleradaptivity_attempts with question_usages based on context ID
        $sql = "
            SELECT aa.*
            FROM {adleradaptivity_attempts} AS aa
            JOIN {question_usages} AS qu ON qu.id = aa.attempt_id
            WHERE qu.contextid = ?
        ";
        return $this->db->get_records_sql($sql, [$modulecontext->id]);
    }
}