<?php

namespace mod_adleradaptivity\external;

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/questionlib.php');

use completion_info;
use context_module;
use core_external\restricted_context_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use invalid_parameter_exception;
use mod_adleradaptivity\local\completion_helpers;
use mod_adleradaptivity\local\helpers;
use moodle_exception;
use question_engine;

class answer_questions extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'element' => new external_single_structure(
                    [
                        'module_id' => new external_value(
                            PARAM_TEXT,
                            'Either module_id or instance_id are required. Module_id of the adaptivity module',
                            VALUE_OPTIONAL
                        ),
                        'instance_id' => new external_value(
                            PARAM_TEXT,
                            'Either module_id or instance_id are required. Instance_id of the adaptivity module',
                            VALUE_OPTIONAL
                        ),
                    ]
                ),
                'questions' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'uuid' => new external_value(
                                PARAM_TEXT,
                                'UUID of the question',
                            ),
                            'answer' => new external_value(
                                PARAM_TEXT,
                                'JSON encoded data containing the question answer. For example for a multiple choice question: [false, false, true, false]. null if the question was not attempted.',
                            ),
                        ]
                    )
                ),
            ]
        );
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'data' => new external_single_structure(
                [
                    'module' => new external_single_structure(
                        [
                            'module_id' => new external_value(
                                PARAM_TEXT,
                                'Either module_id or instance_id are required. Module_id of the adaptivity module',
                                VALUE_OPTIONAL
                            ),
                            'instance_id' => new external_value(
                                PARAM_TEXT,
                                'Either module_id or instance_id are required. Instance_id of the adaptivity module',
                                VALUE_OPTIONAL
                            ),
                            "status" => new external_value(
                                PARAM_TEXT,
                                "Status of the Task, one of correct, incorrect"
                            ),
                        ]
                    ),
                    'tasks' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                "uuid" => new external_value(
                                    PARAM_TEXT,
                                    "UUID of the task"
                                ),
                                "status" => new external_value(
                                    PARAM_TEXT,
                                    "Status of the Task, one of correct, incorrect"
                                ),
                            ]
                        )
                    ),
                    'questions' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                "uuid" => new external_value(
                                    PARAM_TEXT,
                                    "UUID of the question"
                                ),
                                "status" => new external_value(
                                    PARAM_TEXT,
                                    "Status of the Task, one of correct, incorrect, notAttempted"
                                ),
                                "answers" => new external_value(
                                    PARAM_TEXT,
                                    "JSON encoded data containing the question answer. For example for a multiple choice question: array of objects with the fields 'checked' and 'user_answer_correct'. null if the question was not attempted."
                                ),
                            ]
                        )
                    ),
                ]
            )
        ]);
    }

    /**
     * @param array $element [int $module_id, string $instance_id]
     * @param array $questions [array $question]
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function execute(array $element, array $questions): array {
        global $DB;
        $time_at_request_start = time();  // save time here to ensure users are not disadvantaged if processing takes a longer

        // Parameter validation
        $params = self::validate_parameters(self::execute_parameters(), array('element' => $element, 'questions' => $questions));
        $element = $params['element'];
        $questions = $params['questions'];

        // $element has to contain either module_id or instance_id. Both are optional parameters as defined in execute_parameters.
        // - If module_id is given, fetch the module from the database and get the instance_id from it.
        // - If instance_id is given, fetch the module from the database and get the module_id from it.
        // - If none of them is given, throw an exception.
        if (isset($element['module_id'])) {
            $module = get_coursemodule_from_id('adleradaptivity', $element['module_id'], 0, false, MUST_EXIST);
        } else if (isset($element['instance_id'])) {
            $module = get_coursemodule_from_instance('adleradaptivity', $element['instance_id'], 0, false, MUST_EXIST);
        } else {
            throw new invalid_parameter_exception('Either module_id or instance_id has to be given.');
        }
        $module_id = $module->id;
        $instance_id = $module->instance;

        // default validation stuff with context
        $context = context_module::instance($module->id);
        static::validate_context($context);


        // validate all questions are in the given module and save question_bank_entry and task in $questions for later use
        foreach ($questions as $key => $question) {
            // This SQL statement is designed to select all columns from the {tasks} table where there is a matching condition between {questions}, {tasks}, and {question_bank_entries} tables.
            // The statement performs the following:
            // 1. Joins the {question_bank_entries} table with the {questions} table where the 'id' of {question_bank_entries} equals 'question_bank_entries_id' of {questions}.
            // 2. Then, it joins the resultant set with the {tasks} table where 'adleradaptivity_tasks_id' of {questions} equals the 'id' of {tasks}.
            // 3. It filters the result to include rows where 'idnumber' of {question_bank_entries} is "978c2fb5-a947-4d22-8481-5824187d4641" and 'adleradaptivity_id' of {tasks} is 1.
            $sql = "
                SELECT t.*
                FROM {question_bank_entries} qbe
                JOIN {adleradaptivity_questions} q ON qbe.id = q.question_bank_entries_id
                JOIN {adleradaptivity_tasks} t ON q.adleradaptivity_tasks_id = t.id
                WHERE qbe.idnumber = ? AND t.adleradaptivity_id = ?;
            ";
            $adleradaptivity_task = $DB->get_record_sql($sql, [$question['uuid'], $instance_id]);
            if (!$adleradaptivity_task) {
                throw new invalid_parameter_exception('Question with uuid ' . $question['uuid'] . ' is not in the given module.');
            }

            // save $adleradaptivity_task for later use
            $questions[$key]['task'] = $adleradaptivity_task;
        }


        // load attempt
        $quba = helpers::load_or_create_question_usage($module_id);

        // start delegating transaction
        $transaction = $DB->start_delegated_transaction();

        // start processing the questions
        foreach ($questions as $key => $question) {
            // load question object
            $question['question_object'] = helpers::load_question_by_uuid($question['uuid']);

            // switch case over question types. For now only multichoice is supported
            // reformat answer from api format to question type format
            switch ($question['question_object']->qtype) {
                case 'multichoice':
                    // process multichoice question
                    $question['formatted_answer'] = static::format_multichoice_answer($question['answer'], $question['question_object']->single);
                    break;
                default:
                    throw new invalid_parameter_exception('Question type ' . $question['task']->question_type . ' is not supported.');
            }

            // now the formatted answer can be processed like it came from the web interface
            // Also note that answer shuffling is (has to be) disabled for all questions in this module
            $quba->process_action(
                helpers::get_slot_number_by_uuid($question['uuid'], $quba),
                $question['formatted_answer'],
                $time_at_request_start
            );
        }

        // save current questions usage
        question_engine::save_questions_usage_by_activity($quba);

        // Update completion state
        $course = get_course($module->course);
        $completion = new completion_info($course);
        if ($completion->is_enabled($module)) {
            // possibleresult: COMPLETION_COMPLETE prevents setting the completion state to incomplete after it was set to complete
            $completion->update_state($module, COMPLETION_COMPLETE);
        } else {
            throw new moodle_exception('Completion is not enabled for this module.');
        }

        // allow commit
        $transaction->allow_commit();


        // check completion state of questions, tasks and module
        // completion state of module
        $module_completion = ($completion->get_data($module)->completionstate == COMPLETION_COMPLETE || $completion->get_data($module)->completionstate == COMPLETION_COMPLETE_PASS)
            ? 'correct'
            : 'incorrect';

        // completion state of tasks
        $tasks = [];
        foreach ($questions as $question) {
            // check whether $question['task'] is already in $tasks
            foreach ($tasks as $task) {
                if ($task['uuid'] == $question['task']->uuid) {
                    // if it is, skip this question
                    continue 2;
                }
            }
            $tasks[] = [
                'uuid' => $question['task']->uuid,
                'status' => completion_helpers::check_task_completed($quba, $question['task']) ? 'correct' : 'incorrect',
            ];
        }

        // completion state of questions
        $questions_completion = [];
        foreach ($questions as $question) {
            $question_attempt = $quba->get_question_attempt(helpers::get_slot_number_by_uuid($question['uuid'], $quba));
            $questions_completion[] = [
                'uuid' => $question['uuid'],
                'status' => completion_helpers::check_question_correctly_answered($question_attempt) ? 'correct' : 'incorrect',
                'answers' => json_encode(completion_helpers::get_question_answer_details($question_attempt)),
            ];
        }


        return [
            'data' => [
                'module' => [
                    'module_id' => $module_id,
                    'instance_id' => $instance_id,
                    'status' => $module_completion,
                ],
                'tasks' => $tasks,
                'questions' => $questions_completion,
            ]
        ];
    }

    /** Converts the answers from our api format to the format the multichoice question type expects
     *
     * @param string $answer JSON encoded array of booleans
     * @param bool $is_single_choice Whether the question is single choice or not (multiple choice
     * @return array answer string in multichoice format
     * @throws invalid_parameter_exception If the answer has invalid format after json_decode
     *
     *  Format for single choice:
     *  Single choice: $submitteddata = [
     *     '-submit' => "1",   // always set if submitted, otherwise this entry is missing
     *     'answer' => "1",    // selected answer in both cases, submitted and not submitted
     *     'answer' => "-1",   // This was never submitted (before)
     *  ]
     *
     *  Format for multiple choice:
     *  Multiple choice:
     *  $submitteddata = [
     *     '-submit' => "1",   // always set if submitted, otherwise this entry is missing
     *     'choice0' => "0",   // choices are there in all cases, submitted, not submitted and never submitted
     *     'choice1' => "1",
     *     'choice2' => "1",
     *     'choice3' => "0",
     *     'choice4' => "0",
     *  ] // submitted this question
     */
    private static function format_multichoice_answer(string $answer, bool $is_single_choice) {
        // Answer shuffling is no problem because it is disabled for all attempts in this module

        $answers_array = json_decode($answer);

        // Check if decoded value is not only an array, but an array of booleans.
        // if answer is null is no mappable, throw exception
        if (!is_array($answers_array) || !self::all_elements_are_bool($answers_array)) {
            throw new invalid_parameter_exception('Answer has invalid format: ' . json_encode($answersArray));
        }

        $result = ['-submit' => "1"];

        if ($is_single_choice) {
            // if single choice, return the index of the first true value
            $true_index = array_search(true, $answers_array, true);

            if ($true_index === false) {
                throw new invalid_parameter_exception("Invalid answer, no \"true\" value found: " . json_encode($answers_array));
            }

            $result["answer"] = strval($true_index);
        } else {
            // iterate over all answers and set ['choice<index>'] = "1" if true, "0" if false
            foreach ($answers_array as $key => $value) {
                $result['choice' . $key] = $value ? "1" : "0";
            }
        }

        return $result;
    }

    /**
     * Helper function to check if all elements of an array are booleans.
     *
     * @param array $array The array to check.
     * @return bool True if all elements are booleans, false otherwise.
     */
    private static function all_elements_are_bool(array $array): bool {
        foreach ($array as $item) {
            if (!is_bool($item)) {
                return false;
            }
        }
        return true;
    }

}
