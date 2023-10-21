<?php

/**
 * Define all the backup steps that will be used by the backup_adleradaptivity_activity_task
 */


/**
 * Define the complete adleradaptivity structure for backup, with file and id annotations
 */
class backup_adleradaptivity_activity_structure_step extends backup_questions_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $adleradaptivity = new backup_nested_element('adleradaptivity', array('id'), array(
            'name', 'intro', 'introformat', 'adleradaptivity_element_intro', 'timemodified'));
        // TODO: course field (probably added later)

        $tasks = new backup_nested_element('tasks');

        $task = new backup_nested_element('task', array('id'), array(
            'title', 'uuid', 'optional', 'required_difficulty'));

        $questions = new backup_nested_element('questions');

        $question = new backup_nested_element('question', array('id'), array('difficulty'));

        $attempts = new backup_nested_element('attempts');

        $attempt = new backup_nested_element('attempt', array('id'), array(
            'attempt_id', 'user_id'));

        // Build the tree
        $adleradaptivity->add_child($tasks);
        $tasks->add_child($task);

        $task->add_child($questions);
        $questions->add_child($question);

        $adleradaptivity->add_child($attempts);
        $attempts->add_child($attempt);

        // Define sources
        $adleradaptivity->set_source_table('adleradaptivity', array('id' => backup::VAR_ACTIVITYID));
        $task->set_source_table('adleradaptivity_tasks', array('adleradaptivity_id' => backup::VAR_ACTIVITYID));
        $question->set_source_sql('
                SELECT aq.* 
                FROM {adleradaptivity_questions} aq
                JOIN {adleradaptivity_tasks} at ON aq.adleradaptivity_task_id = at.id
                WHERE aq.adleradaptivity_task_id = :adleradaptivity_task_id; 
            ',
            ['adleradaptivity_task_id' => backup::VAR_PARENTID]
        );

        $this->add_question_references($question, 'mod_adleradaptivity', 'question');

        $this->add_question_set_references($question, 'mod_adleradaptivity', 'question');

        if ($userinfo) {
            $attempt->set_source_sql('
                    SELECT aa.* 
                    FROM {adleradaptivity_attempts} aa
                    JOIN {question_usages} qu ON aa.attempt_id = qu.id
                    WHERE qu.contextid = :context_id;
                ',
                ['context_id' => backup::VAR_CONTEXTID]
            );
        }

        // Define id annotations
        $attempt->annotate_ids('user', 'user_id');

        // Define file annotations

        // Return the root element (adleradaptivity), wrapped into standard activity structure
        return $this->prepare_activity_structure($adleradaptivity);
    }
}