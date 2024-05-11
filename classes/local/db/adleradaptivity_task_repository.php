<?php

namespace mod_adleradaptivity\local\db;

use dml_exception;
use moodle_database;
use moodle_exception;
use stdClass;

class adleradaptivity_task_repository extends base_repository {
    /**
     * @throws dml_exception
     */
    public function create_task(stdClass $task): bool|int {
        return $this->db->insert_record('adleradaptivity_tasks', $task);
    }

    /**
     * @throws dml_exception
     */
    public function delete_task_by_id(int $task_id): bool {
        return $this->db->delete_records('adleradaptivity_tasks', ['id' => $task_id]);
    }

    /**
     * Get task by question uuid
     *
     * @param string $question_uuid The question uuid
     * @param int $instance_id The instance id of the adleradaptivity activity
     * @returns stdClass The task object
     * @throws moodle_exception If the task could not be found or if there are multiple results.
     */
    public function get_task_by_question_uuid($question_uuid, $instance_id) {
        // uuid is stored in question_bank_entries table.
        // connection to adleradaptivity_questions over question_references
        // quesiton_references need additional filtering for entries of this module.
        // filter for questionarea is currently not really neaded as this feature is not used here.
        // version has to be 1 as quesiton versioning is not supported by this module.
        $sql = "
            SELECT t.*
            FROM {question_bank_entries} qbe
            JOIN {question_references} qr ON qbe.id = qr.questionbankentryid
            JOIN {adleradaptivity_questions} aq ON qr.itemid = aq.id
            JOIN {adleradaptivity_tasks} t ON aq.adleradaptivity_task_id = t.id
            
            WHERE qr.component = 'mod_adleradaptivity'
            AND qr.questionarea = 'question'
            AND qr.version = 1
            AND qbe.idnumber = :question_uuid
            AND t.adleradaptivity_id = :instance_id;
        ";

        return $this->db->get_record_sql(
            $sql,
            ['question_uuid' => $question_uuid, 'instance_id' => $instance_id],
            MUST_EXIST
        );
    }

    /**
     * @throws dml_exception
     */
    public function get_tasks_by_adleradaptivity_id($adleradaptivity_instance_id): array {
        return $this->db->get_records('adleradaptivity_tasks', ['adleradaptivity_id' => $adleradaptivity_instance_id]);
    }
}