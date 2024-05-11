<?php

namespace mod_adleradaptivity\local\db;

use context_module;
use dml_exception;
use stdClass;

class adleradaptivity_question_repository extends base_repository {
    /**
     * @throws dml_exception
     */
    public function create_question(stdClass $question): bool|int {
        return $this->db->insert_record('adleradaptivity_questions', $question);
    }

    /**
     * @throws dml_exception
     */
    public function delete_question_by_id(int $question_id): bool {
        return $this->db->delete_records('adleradaptivity_questions', ['id' => $question_id]);
    }

    /**
     * Get adleradaptivity question by question_bank_entries_id
     *
     * @param int $question_bank_entries_id
     * @return stdClass question object
     * @throws dml_exception
     */
    public function get_adleradaptivity_question_by_question_bank_entries_id(int $question_bank_entries_id, context_module $module_context) {
        $sql = "
            SELECT aq.*
            FROM {adleradaptivity_questions} aq
            JOIN {question_references} qr ON qr.itemid = aq.id
            WHERE qr.questionbankentryid = :questionbankentryid
            AND qr.usingcontextid = :usingcontextid
        ";

        return $this->db->get_record_sql($sql, ['questionbankentryid' => $question_bank_entries_id, 'usingcontextid' => $module_context->id], MUST_EXIST);
    }
}