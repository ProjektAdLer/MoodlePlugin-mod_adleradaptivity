<?php

namespace mod_adleradaptivity\local\db;

use context_module;
use dml_exception;
use moodle_exception;
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

    /** Get adleradaptivity questions with moodle question ids for the given adleradaptivity task id.
     *
     * @param int $task_id task id of the adleradaptivity task.
     * @param bool $ignore_question_version DANGER! whether to ignore the question version. If true,
     * the question version is not checked. Besides that questions with version != 1 are not supported by adleradaptivity,
     * deactivating this check might also result in the same question being returned multiple times in different versions.
     * This switch is intended only for stuff like module deletion.
     * @return array of objects with moodle question id and adleradaptivity question id.
     * @throws moodle_exception if any question version is not equal to 1.
     */
    public function get_adleradaptivity_questions_with_moodle_question_id_by_task_id(int $task_id, bool $ignore_question_version = false): array {
        // Retrieves question versions from the {question_versions} table based on a specified adaptivity ID.
        $sql = "
            SELECT qv.questionid, qv.version, aq.*
            FROM {adleradaptivity_questions} aq
            JOIN {question_references} qr ON qr.itemid = aq.id
            JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
            
            WHERE aq.adleradaptivity_task_id = ?
            AND qr.component = 'mod_adleradaptivity'
            AND qr.questionarea = 'question';
        ";
        $question_data = $this->db->get_records_sql($sql, [$task_id]);

        $result = [];
        foreach ($question_data as $one_question) {
            if (!$ignore_question_version && $one_question->version != 1) {
                throw new moodle_exception(
                    'question_version_not_one',
                    'mod_adleradaptivity',
                    '',
                    '',
                    'There is a question with version ' . $one_question->version . '. This is not supported by adleradaptivity.'
                );
            }

            // remove version field from $one_question
            unset($one_question->version);

            $result[] = $one_question;
        }

        return $result;
    }

}