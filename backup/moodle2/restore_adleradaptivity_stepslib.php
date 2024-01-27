<?php

/**
 * Structure step to restore one adleradaptivity activity
 */
class restore_adleradaptivity_activity_structure_step extends restore_questions_activity_structure_step {

    private ?object $current_adleradaptivity_attempt;

    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('adleradaptivity', '/activity/adleradaptivity');
        $paths[] = new restore_path_element('task', '/activity/adleradaptivity/tasks/task');

        $question = new restore_path_element('question', '/activity/adleradaptivity/tasks/task/questions/question');
        $paths[] = $question;
        $this->add_question_references($question, $paths);
        $this->add_question_set_references($question, $paths);


        if ($userinfo) {
            $adleradaptivity_attempt = new restore_path_element('adleradaptivity_attempt', '/activity/adleradaptivity/attempts/attempt');
            $paths[] = $adleradaptivity_attempt;

            // Add states and sessions
            $this->add_question_usages($adleradaptivity_attempt, $paths);
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_adleradaptivity($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        // default values for timecreated and timemodified, if they are not set
        if (!isset($data->timemodified)) {
            $data->timemodified = time();
        }

        // insert the adleradaptivity record
        $newitemid = $DB->insert_record('adleradaptivity', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_task($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->adleradaptivity_id = $this->get_new_parentid('adleradaptivity');

        $newitemid = $DB->insert_record('adleradaptivity_tasks', $data);
        $this->set_mapping('task', $oldid, $newitemid);
    }

    protected function process_question($data) {
        global $DB;

        $data = (object)$data;

        $data->adleradaptivity_task_id = $this->get_new_parentid("task");


        $newitemid = $DB->insert_record('adleradaptivity_questions', $data);
        $this->set_mapping('question', $data->id, $newitemid);
    }

    /**
     * Implementation of parent class is bugged. It hardcoded quiz module references.
     *
     * Process question references which replaces the direct connection to quiz slots to question.
     *
     * @param array $data the data from the XML file.
     */
    public function process_question_reference($data) {
        global $DB;
        $data = (object)$data;
        $data->usingcontextid = $this->get_mappingid('context', $data->usingcontextid);
        $data->itemid = $this->get_new_parentid('question');
        if ($entry = $this->get_mappingid('question_bank_entry', $data->questionbankentryid)) {
            $data->questionbankentryid = $entry;
        }
        $DB->insert_record('question_references', $data);
    }

    protected function after_execute() {
    }

    protected function process_adleradaptivity_attempt($data) {
        $data = (object)$data;

        // Get user mapping, return early if no mapping found for the quiz attempt.
        $olduserid = $data->user_id;
        $data->user_id = $this->get_mappingid('user', $olduserid, 0);
        if ($data->user_id === 0) {
            $this->log('Mapped user ID not found for user ' . $olduserid . ', adleradaptivity ' . $this->get_new_parentid('adleradaptivity') .
                ', attempt ' . $data->attempt . '. Skipping adleradaptivity attempt', backup::LOG_INFO);

            $this->current_adleradaptivity_attempt = null;
            return;
        }

        // The data is actually inserted into the database later in inform_new_usage_id.
        $this->current_adleradaptivity_attempt = clone($data);
    }

    /**
     * This is called after the question_usages are inserted into the database.
     * It is used to insert the adleradaptivity attempt with the attempt_id of the newly created question usage into the database.
     *
     * @param int $newusageid the id of the newly created question usage.
     */
    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->current_adleradaptivity_attempt;
        if ($data === null) {
            return;
        }

        $oldid = $data->id;
        $data->attempt_id = $newusageid;

        $newitemid = $DB->insert_record('adleradaptivity_attempts', $data);

        // Save adleradaptivity attempt id mapping
        $this->set_mapping('adleradaptivity', $oldid, $newitemid);
    }
}