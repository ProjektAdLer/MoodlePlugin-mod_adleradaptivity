<?php

namespace mod_adleradaptivity\local\db;

use context_module;
use dml_exception;
use stdClass;

class adleradaptivity_question_repository extends base_repository {
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

        // TODO: this fails if there are multiple references to the same question (e.g. cloning a module)
        // Multiple records found, only one record expected.
        // Error code: multiplerecordsfound

        // questions are loaded from question_usage. This questions are then tried to be matched to adleradaptivity_questions.
        // This expects that there is only one question_reference per question.
        //
        // -> 1) verify question is only referenced once in the current module instance
        // -> 2) additional condition in sql query to only get the question_reference for the current module instance
        // -> 2b) load all questions via the modules database (instance -> tasks -> questions -> moodle questions) and verify it is in the question_usage (cross check both directions)

        // Update: done 2
        // TODO: test that tests this new behavior (for testing: remove `AND qr.usingcontextid = ?` and `, 'usingcontextid' => $module_context->id`
        // TODO: 1

        return $this->db->get_record_sql($sql, ['questionbankentryid' => $question_bank_entries_id, 'usingcontextid' => $module_context->id], MUST_EXIST);
    }
}