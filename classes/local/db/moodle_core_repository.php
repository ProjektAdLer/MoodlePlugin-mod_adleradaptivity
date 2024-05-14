<?php

namespace mod_adleradaptivity\local\db;

use dml_exception;
use dml_missing_record_exception;
use stdClass;

class moodle_core_repository extends base_repository {
    public function create_question_reference(stdClass $question_reference): int {
        return $this->db->insert_record('question_references', $question_reference);
    }

    /**
     * Retrieves the course module ID (cmid) for a given question usage ID.
     *
     * @param int $quid The ID of the question usage.
     * @return int The course module ID (cmid) associated with the question usage.
     * @throws dml_exception If there's an error with the database query.
     * @throws dml_missing_record_exception If the expected records are not found.
     */
    public function get_cmid_by_question_usage_id(int $quid): int {
        // First, retrieve the contextid from the question_usages table using the question usage ID
        $contextid = $this->db->get_field('question_usages', 'contextid', ['id' => $quid], MUST_EXIST);

        if (!$contextid) {
            throw new dml_missing_record_exception('context not found for the provided question usage ID');
        }

        // Now, use the contextid to find the corresponding cmid in the context table
        // Note: CONTEXT_MODULE is a constant equal to 80, representing the context level for course modules in Moodle.
        // instance id refers to the instance of the context level. For CONTEXT_MODULE, this is the course module ID.
        $cmid = $this->db->get_field_select('context', 'instanceid', "contextlevel = ? AND id = ?", [CONTEXT_MODULE, $contextid]);

        if (!$cmid) {
            throw new dml_missing_record_exception('course module (cmid) not found for the provided context ID');
        }

        return $cmid;
    }

    /**
     * @throws dml_exception
     */
    public function get_question_versions_by_adleradaptivity_instance_id(int $instance_id): array {
        $sql = "
        SELECT qv.*
        FROM {adleradaptivity_questions} aq
        JOIN {question_references} qr ON qr.itemid = aq.id
        JOIN {adleradaptivity_tasks} at ON aq.adleradaptivity_task_id = at.id
        JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
        
        WHERE at.adleradaptivity_id = ?
        AND qr.component = 'mod_adleradaptivity'
        AND qr.questionarea = 'question'
        ";
        return $this->db->get_records_sql($sql, [$instance_id]);
    }
}
