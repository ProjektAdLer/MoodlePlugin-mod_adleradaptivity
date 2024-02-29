<?php

global $CFG;

class mod_adleradaptivity_generator extends testing_module_generator {
    public function create_instance($record = null, array $options = null) {
        $default_params = [
            'name' => 'name',
            'intro' => 'intro',
            'adaptivity_element_intro' => 'adaptivity_element_intro',
        ];
        $record = array_merge($default_params, $record);

        return parent::create_instance($record, (array)$options);
    }

    public function create_mod_adleradaptivity_task(int $adleradaptivity_id, $params = array(), bool $insert = true) {
        global $DB;
        $default_params = [
            'adleradaptivity_id' => $adleradaptivity_id,
            'title' => 'title',
            'uuid' => 'uuid',
            'required_difficulty' => 100,
        ];
        $params = array_merge($default_params, $params);
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_tasks', $new_object);
        }
        return $new_object;
    }

    public function create_mod_adleradaptivity_question(int $adleradaptivity_task_id, $params = array(), bool $insert = true) {
        global $DB;
        $default_params = [
            'adleradaptivity_task_id' => $adleradaptivity_task_id,
            'difficulty' => 100,
        ];
        $params = array_merge($default_params, $params);
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_questions', $new_object);
        }
        return $new_object;
    }

    /**
     * Create a question reference for a given moodle questionbank entry and adleradaptivity question
     * on a given adleradaptivity module.
     *
     * @param int $adleradaptivity_question_id The id of the adleradaptivity question.
     * @param int $questionbank_entry_id The id of the moodle questionbank entry.
     * @param int $cmid The id of the adleradaptivity module.
     * @param bool $insert If true, the question reference will be inserted into the database.
     * @return object Returns the question reference object.
     */
    public function create_question_reference(int $adleradaptivity_question_id, int $questionbank_entry_id, int $cmid, bool $insert = true) {
        global $DB;
        $new_object = (object)[
            'usingcontextid' => context_module::instance($cmid)->id,
            'component' => 'mod_adleradaptivity',
            'questionarea' => 'question',
            'itemid' => $adleradaptivity_question_id,
            'questionbankentryid' => $questionbank_entry_id,
            'version' => 1,
        ];

        if ($insert) {
            $new_object->id = $DB->insert_record('question_references', $new_object);
        }
        return $new_object;
    }

    /**
     * Create a moodle core question for the adleradaptivity module.
     *
     * @param int $question_category_id The id of the question category to add the question to.
     * @param bool $singlechoice If true, the question will be a single choice question. If false, the question will be a multiple choice question.
     * @param string $name The name of the question.
     * @param string $uuid The uuid of the question.
     *
     * @return question_definition Returns the question object.
     */
    public function create_moodle_question(int $question_category_id, bool $singlechoice, string $name, string $uuid, string|null $question_text = null) {
        global $DB;
        $generator = $this->datagenerator->get_plugin_generator('core_question');
        $question_override_parameter = [
            'name' => $name,
            'category' => $question_category_id,
            'idnumber' => $uuid,
        ];
        if ($question_text) {
            $question_override_parameter['questiontext'] = ['text' => $question_text, 'format' => FORMAT_HTML];
        }

        $q_generated = $generator->create_question(
            'multichoice',
            $singlechoice ? 'one_of_four' : null,
            $question_override_parameter);
        $question = question_bank::load_question($q_generated->id);

        // patch question answers to give penalty if wrong
        $answers = $question->answers;
        foreach ($answers as $answer) {
            $answer->fraction = $answer->fraction <= 0 ? -.5 : $answer->fraction;
            $DB->update_record('question_answers', $answer);
        }
        question_bank::notify_question_edited($question->id);

        return $question;
    }

    public function create_mod_adleradaptivity_attempt(int $attempt_id, int $user_id, bool $insert = true) {
        global $DB;
        $default_params = [
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
        ];
//        $params = array_merge($default_params, $params);
        $params = $default_params;
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_attempts', $new_object);
        }
        return $new_object;
    }
}