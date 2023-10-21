<?php

// TODO: Attempts
//  question_usages
//  question_attempts

// TODO: Questions

/**
 * Structure step to restore one adleradaptivity activity
 */
class restore_adleradaptivity_activity_structure_step extends restore_questions_activity_structure_step {

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
            $paths[] = new restore_path_element('adleradaptivity_attempt', '/activity/adleradaptivity/attempts/attempt');
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

        $data->adleradaptivity_tasks_id = $this->get_new_parentid("task");
        // TODO: question_bank_entries_id mapping

        $newitemid = $DB->insert_record('adleradaptivity_questions', $data);
        $this->set_mapping('question', $data->id, $newitemid);
    }

    protected function process_adleradaptivity_attempt($data) {
        // TODO: Implement process_adleradaptivity_attempt() method.
//        global $DB;
//
//        $data = (object)$data;
//
//        $data->adleradaptivityid = $this->get_new_parentid('adleradaptivity');
//
//        $newitemid = $DB->insert_record('adleradaptivity_attempts', $data);
//        $this->set_mapping('adleradaptivity_attempt', $oldid, $newitemid);
    }

    /**
     * Implementatin of parent class is bugged. It hardcodes quiz module references.
     * TODO
     * Process question references which replaces the direct connection to quiz slots to question.
     *
     * @param array $data the data from the XML file.
     */
    public function process_question_reference($data) {
        global $DB;
        $data = (object) $data;
        $data->usingcontextid = $this->get_mappingid('context', $data->usingcontextid);
        $data->itemid = $this->get_new_parentid('question');
        if ($entry = $this->get_mappingid('question_bank_entry', $data->questionbankentryid)) {
            $data->questionbankentryid = $entry;
        }
        $DB->insert_record('question_references', $data);
    }

    protected function after_execute() {}

    protected function inform_new_usage_id($newusageid) {
        // TODO: Implement inform_new_usage_id() method.
        // required for question bank import (questions activity)
    }
}