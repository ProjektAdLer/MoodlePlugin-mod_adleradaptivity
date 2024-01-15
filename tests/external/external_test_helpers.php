<?php

namespace mod_adleradaptivity\external;

use context_module;
use moodle_exception;
use question_bank;
use stdClass;
use testing_data_generator;

class external_test_helpers {
    /**
     * This function creates a course with test questions, enrolls a user, and sets up tasks in the context of a Moodle course.
     * It creates an adleradaptivity module with two tasks, and 1 or 2 questions multichoice/singlechoice questions for the first tasks. 
     *
     * @param testing_data_generator $generator The testing data generator object.
     * @param bool $task_required If true, the task will be required. If false, the task will not be required.
     * @param bool $singlechoice If true, the question will be a single choice question. If false, the question will be a multiple choice question.
     * @param bool $q2 If true, a second question will be created. If false, a second question will not be created.
     * @return array Returns an array containing the user, the first question (q1), and the second question (q2) if it was created.
     */
    public static function create_course_with_test_questions(testing_data_generator $generator, $task_required = true, $singlechoice = false, $q2 = false) {
        global $DB;

        $adleradaptivity_generator = $generator->get_plugin_generator('mod_adleradaptivity');

        $uuid = '75c248df-562f-40f7-9819-ebbeb078954b';
        $uuid2 = '75c248df-562f-40f7-9819-ebbeb0789540';

        // create user, course and enrol user
        $user = $generator->create_user();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $generator->enrol_user($user->id, $course->id);

        // create adleradaptivity module
        $adleradaptivity_module = $generator->create_module('adleradaptivity', ['course' => $course->id, 'completion' => 2]);
        $adleradaptivity_task = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => $task_required ? 100 : null]);
        $adleradaptivity_task2 = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => null, 'uuid' => 'uuid2', 'name' => 'task2']);
        $adleradaptivity_question = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id);
        if ($q2) {
            $adleradaptivity_question2 = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id, ['difficulty' => 200]);
        }

        // create question
        $generator = $generator->get_plugin_generator('core_question');
        $qcat = $generator->create_question_category(['name' => 'My category', 'sortorder' => 1, 'idnumber' => 'myqcat']);
        $q1_generated = $generator->create_question('multichoice', $singlechoice ? 'one_of_four' : null, ['name' => 'q1', 'category' => $qcat->id, 'idnumber' => $uuid]);
        $question1 = question_bank::load_question($q1_generated->id);
        if ($q2) {
            $q2_generated = $generator->create_question('multichoice', $singlechoice ? 'one_of_four' : null, ['name' => 'q2', 'category' => $qcat->id, 'idnumber' => $uuid2]);
            $question2 = question_bank::load_question($q2_generated->id);
        }

        // patch q1 answers to give penalty if wrong
        $answers = $question1->answers;
        foreach ($answers as $answer) {
            $answer->fraction = $answer->fraction <= 0 ? -.5 : $answer->fraction;
            $DB->update_record('question_answers', $answer);
        }
        question_bank::notify_question_edited($question1->id);

        // create question reference
        $questionreferences = new stdClass();
        $questionreferences->usingcontextid = context_module::instance($adleradaptivity_module->cmid)->id;
        $questionreferences->component = 'mod_adleradaptivity';
        $questionreferences->questionarea = 'question';
        $questionreferences->itemid = $adleradaptivity_question->id;
        $questionreferences->questionbankentryid = $question1->questionbankentryid;
        $questionreferences->version = 1;
        $DB->insert_record('question_references', $questionreferences);
        if ($q2) {
            $questionreferences2 = new stdClass();
            $questionreferences2->usingcontextid = context_module::instance($adleradaptivity_module->cmid)->id;
            $questionreferences2->component = 'mod_adleradaptivity';
            $questionreferences2->questionarea = 'question';
            $questionreferences2->itemid = $adleradaptivity_question2->id;
            $questionreferences2->questionbankentryid = $question2->questionbankentryid;
            $questionreferences2->version = 1;
            $DB->insert_record('question_references', $questionreferences2);
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

    public static function generate_answer_question_parameters(String $q1, String $q2, array $course_data) {
        // generate answer data
        $answerdata_q1 = [];
        $partially_one_correct_chosen = false;
        foreach ($course_data['q1']['question']->answers as $answer) {
            switch ($q1) {
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


        // generate parameters
        $param_module = [
            'instance_id' => $course_data['module']->id,
        ];
        $param_questions = [
            [
                'uuid' => $course_data['q1']['uuid'],
                'answer' => json_encode($answerdata_q1),
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