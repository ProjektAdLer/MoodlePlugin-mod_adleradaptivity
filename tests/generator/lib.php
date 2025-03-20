<?php

global $CFG;

class mod_adleradaptivity_generator extends testing_module_generator {
    /** This method is called by $this->getDataGenerator()->create_module('adleradaptivity', ...
     *
     * @param $record
     * @param array|null $options
     * @return stdClass
     * @throws coding_exception
     */
    public function create_instance($record = null, array $options = null): stdClass {
        $default_params = [
            'name' => 'name',
            'intro' => 'intro',
            'adaptivity_element_intro' => 'adaptivity_element_intro',
        ];
        $record = array_merge($default_params, $record);

        return parent::create_instance($record, (array)$options);
    }

    public function create_mod_adleradaptivity_task(int $adleradaptivity_id, $params = array(), bool $insert = true): object {
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

    public function create_mod_adleradaptivity_question(int $adleradaptivity_task_id, $params = array(), bool $insert = true): object {
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
    public function create_question_reference(int $adleradaptivity_question_id, int $questionbank_entry_id, int $cmid, bool $insert = true): object {
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
    public function create_moodle_question(int $question_category_id, bool $singlechoice, string $name, string $uuid, string|null $question_text = null): question_definition {
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

    public function create_mod_adleradaptivity_attempt(int $attempt_id, int $user_id, int $adleradaptivity_id, bool $insert = true): object {
        global $DB;
        $default_params = [
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
            'adleradaptivity_id' => $adleradaptivity_id,
        ];
//        $params = array_merge($default_params, $params);
        $params = $default_params;
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_attempts', $new_object);
        }
        return $new_object;
    }

    /**
     * This function creates a course with test questions, enrolls a user, and sets up tasks in the context of a Moodle course.
     * It creates an adleradaptivity module with two tasks, and 1 or 2 questions multichoice/singlechoice questions for the first tasks.
     *
     * @param testing_data_generator $generator The testing data generator object.
     * @param bool $task_required If true, the task will be required. If false, the task will not be required.
     * @param bool $singlechoice If true, the question will be a single choice question. If false, the question will be a multiple choice question.
     * @param bool $q2 If true, a second question will be created. If false, a second question will not be created.
     * @param string $prefix Used in everything that should be unique. Use if calling this function multiple times in a test.
     * @return array Returns an array containing the user, the first question (q1), and the second question (q2) if it was created.
     */
    public function create_course_with_test_questions(testing_data_generator $generator, bool $task_required = true, bool $singlechoice = false, bool $q2 = false, string $prefix = ""): array {
        $adleradaptivity_generator = $generator->get_plugin_generator('mod_adleradaptivity');

        $uuid = '75c248df-562f-40f7-9819-ebbeb078954b' . $prefix;
        $uuid2 = '75c248df-562f-40f7-9819-ebbeb0789540' . $prefix;

        // create user, course and enrol user
        $user = $generator->create_user();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $generator->enrol_user($user->id, $course->id);

        // create adleradaptivity module
        $adleradaptivity_module = $generator->create_module('adleradaptivity', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC]);
        $adleradaptivity_task = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => $task_required ? 100 : null, 'uuid' => 'uuid1' . $prefix]);
        $adleradaptivity_task2 = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => null, 'uuid' => 'uuid2' . $prefix, 'name' => 'task2']);
        $adleradaptivity_question = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id);
        if ($q2) {
            $adleradaptivity_question2 = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id, ['difficulty' => 200]);
        }

        // create question
        $generator = $generator->get_plugin_generator('core_question');
        $qcat = $generator->create_question_category(['name' => 'My category' . $prefix, 'sortorder' => 1, 'idnumber' => 'myqcat' . $prefix]);
        $question1 = $adleradaptivity_generator->create_moodle_question($qcat->id, $singlechoice, 'q1', $uuid);
        if ($q2) {
            $question2 = $adleradaptivity_generator->create_moodle_question($qcat->id, $singlechoice, 'q2', $uuid2);
        }

//      create question reference
        $adleradaptivity_generator->create_question_reference($adleradaptivity_question->id, $question1->questionbankentryid, $adleradaptivity_module->cmid);
        if ($q2) {
            $adleradaptivity_generator->create_question_reference($adleradaptivity_question2->id, $question2->questionbankentryid, $adleradaptivity_module->cmid);
        }

        return [
            'user' => $user,
            'q1' => [
                'uuid' => $uuid,
                'question' => $question1,
                'adleradaptivity_question' => $adleradaptivity_question,
            ],
            'q2' => $q2 ? [
                'uuid' => $uuid2,
                'question' => $question2,
                'adleradaptivity_question' => $adleradaptivity_question2,
            ] : null,
            'module' => $adleradaptivity_module,
            'course' => $course,
        ];
    }

    /**
     *
     * @param String $answer_type How to answer the question. Can be 'correct', 'incorrect', 'all_true', 'all_false', 'partially_correct'.
     * @param question_definition $question
     * @return array Returns an array of booleans representing the answers to the question.
     * @throws moodle_exception
     */
    public function gernerate_question_answers_for_single_question(String $answer_type, question_definition $question): array {
        $answerdata_q1 = [];
        $partially_one_correct_chosen = false;
        foreach ($question->answers as $answer) {
            switch ($answer_type) {
                case 'correct':
                    $answerdata_q1[] = $answer->fraction > 0;
                    break;
                case 'incorrect':
                    $answerdata_q1[] = $answer->fraction <= 0;
                    break;
                case 'all_true':
                    $answerdata_q1[] = true;
                    break;
                case 'all_false':
                    $answerdata_q1[] = false;
                    break;
                case 'partially_correct':
                    if ($partially_one_correct_chosen) {
                        $answerdata_q1[] = $answer->fraction == 0;
                    } else {
                        $answerdata_q1[] = $answer->fraction > 0;
                        $partially_one_correct_chosen = true;
                    }
                    break;
                default:
                    throw new moodle_exception('invalid_test_data', 'adleradaptivity');
            }
        }
        return $answerdata_q1;
    }

    public function generate_answer_question_parameters(String $q1, String $q2, array $course_data): array {
        // generate parameters
        $param_module = [
            'instance_id' => $course_data['module']->id,
        ];
        $param_questions = [
            [
                'uuid' => $course_data['q1']['uuid'],
                'answer' => json_encode(self::gernerate_question_answers_for_single_question($q1, $course_data['q1']['question']))
            ]
        ];
        if ($q2 == 'answered') {
            $param_questions[] = [
                'uuid' => $course_data['q2']['uuid'],
                'answer' => json_encode([true, false, false, false]),
            ];
        }

        return [$param_module, $param_questions];
    }
}