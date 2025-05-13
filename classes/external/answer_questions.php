<?php

namespace mod_adleradaptivity\external;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use completion_info;
use context_module;
use core\di;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;
use dml_exception;
use dml_transaction_exception;
use invalid_parameter_exception;
use mod_adleradaptivity\local\completion_helpers;
use mod_adleradaptivity\local\db\adleradaptivity_task_repository;
use mod_adleradaptivity\local\helpers;
use mod_adleradaptivity\moodle_core;
use moodle_database;
use moodle_exception;
use question_engine;
use question_usage_by_activity;
use stdClass;

class answer_questions extends external_api {
    private static string $question_engine = question_engine::class;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
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
                                "Status of the Module, one of". completion_helpers::STATUS_CORRECT . ", " . completion_helpers::STATUS_INCORRECT . ", " . completion_helpers::STATUS_NOT_ATTEMPTED
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
                                    "Status of the Task, one of". completion_helpers::STATUS_CORRECT . ", " . completion_helpers::STATUS_INCORRECT . ", " . completion_helpers::STATUS_NOT_ATTEMPTED
                                ),
                            ]
                        )
                    ),
                    'questions' => external_helpers::get_external_structure_question_response(),
                ]
            )
        ]);
    }

    /**
     * Executes question processing for a given module and updates their completion status.
     *
     * @param array $module Array containing module details (module_id, instance_id).
     * @param array $questions Array of questions to be processed.
     * @return array Associative array containing the updated module, tasks, and question completion data.
     * @throws invalid_parameter_exception If parameters are invalid or questions don't exist.
     * @throws dml_exception For database-related errors.
     * @throws restricted_context_exception If the user context is not valid for accessing the module.
     * @throws moodle_exception For general Moodle-related errors.
     */
    public static function execute(array $module, array $questions): array {
        $time_at_request_start = time();

        $params = self::validate_parameters(self::execute_parameters(), ['module' => $module, 'questions' => $questions]);
        $module = external_helpers::validate_module_params_and_get_module($params['module']);
        $context = context_module::instance($module->id);

        // permission checks
        static::validate_context($context);
        require_capability('mod/adleradaptivity:create_and_edit_own_attempt', $context);

        $questions = self::validate_and_enhance_questions($questions, $module->instance);

        $quba = helpers::load_or_create_question_usage($module->id);
        $completion = self::process_questions($questions, $time_at_request_start, $module, $quba);

        $module_completion_status = helpers::determine_module_completion_status($completion, $module);
        $tasks_completion_data = static::get_tasks_completion_data($questions, $quba);
        $questions_completion_data = external_helpers::generate_question_response_data(array_column($questions, 'uuid'), $quba);

        return [
            'data' => [
                'module' => [
                    'module_id' => $module->id,
                    'instance_id' => $module->instance,
                    'status' => $module_completion_status,
                ],
                'tasks' => $tasks_completion_data,
                'questions' => $questions_completion_data,
            ]
        ];
    }

    /**
     * Validates the questions and enhances them with necessary task data.
     *
     * @param array $questions Questions array.
     * @param string $instance_id Instance ID of the module.
     * @return array Enhanced questions array with task data.
     * @throws invalid_parameter_exception If a question does not exist.
     */
    protected static function validate_and_enhance_questions(array $questions, string $instance_id): array {
        $task_repository = di::get(adleradaptivity_task_repository::class);
        foreach ($questions as $key => $question) {
            try {
                $task = $task_repository->get_task_by_question_uuid($question['uuid'], $instance_id);
                $questions[$key]['task'] = $task;
            } catch (moodle_exception $e) {
                throw new invalid_parameter_exception('Question with uuid ' . $question['uuid'] . ' does not exist.');
            }
        }
        return $questions;
    }

    /**
     * Retrieves tasks completion data.
     *
     * @param array $questions Questions array.
     * @param question_usage_by_activity $quba Question usage by activity object.
     * @return array Tasks completion data.
     * @throws moodle_exception
     */
    protected static function get_tasks_completion_data(array $questions, question_usage_by_activity $quba): array {
        $tasks = [];
        foreach ($questions as $question) {
            foreach ($tasks as $task) {
                if ($task->uuid == $question['task']->uuid) {
                    continue 2;
                }
            }
            $tasks[] = external_helpers::generate_task_response_data($quba, $question['task']);
        }
        return $tasks;
    }


    /** Converts the answers from our api format to the format the multichoice question type expects
     *
     * @param string $answer JSON encoded array of booleans
     * @param bool $is_single_choice Whether the question is single choice or not (multiple choice
     * @param int|null $number_of_choices Validate the number of choices the question should have. If it is not the same, throw an exception. If it is null, do not validate.
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
    private static function format_multichoice_answer(string $answer, bool $is_single_choice, ?int $number_of_choices = null): array {
        // Answer shuffling is no problem because it is disabled for all attempts in this module

        $answers_array = json_decode($answer);

        // Check if decoded value is not only an array, but an array of booleans.
        // if answer is null is no mappable, throw exception
        if (!is_array($answers_array) || !self::all_elements_are_bool($answers_array)) {
            throw new invalid_parameter_exception('Answer has invalid format: ' . json_encode($answers_array));
        }

        // if $number_of_choices is set, check if the number of choices is correct
        if ($number_of_choices !== null && count($answers_array) != $number_of_choices) {
            throw new invalid_parameter_exception('Answer has invalid number of choices: ' . json_encode($answers_array));
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

    /**
     * Processes the questions, updates the question_usage and the completion state of the module
     *
     * @param array $questions Array of questions to be processed.
     * @param int $time_at_request_start Timestamp at the start of the request.
     * @param stdClass $module Moodle module instance.
     * @param moodle_database $DB Moodle database instance.
     * @param question_usage_by_activity $quba Question usage by activity object.
     * @return completion_info Completion information after processing questions.
     * @throws invalid_parameter_exception If an unsupported question type is encountered.
     * @throws moodle_exception If completion is not enabled for the module.
     * @throws dml_transaction_exception
     * @throws dml_exception
     */
    protected static function process_questions(array $questions, int $time_at_request_start, stdClass $module, question_usage_by_activity $quba): completion_info {
        // start delegating transaction
        $transaction = di::get(moodle_database::class)->start_delegated_transaction();

        // start processing the questions
        foreach ($questions as $key => $question) {
            self::process_single_question($question, $time_at_request_start, $quba);
        }

        // save current questions usage
        self::$question_engine::save_questions_usage_by_activity($quba);

        // Update completion state
        $completion = self::update_module_completion($module);

        // allow commit
        $transaction->allow_commit();

        return $completion;
    }

    /**
     * Processes an individual question.
     *
     * @param array $question Question data containing: uuid, answer (json encoded array of booleans)
     * @param int $time_at_request_start Timestamp at the start of the request.
     * @param question_usage_by_activity $quba Question usage by activity object.
     * @throws invalid_parameter_exception If an unsupported question type is encountered.
     */
    public static function process_single_question(array $question, int $time_at_request_start, question_usage_by_activity $quba): void {
        $question['question_object'] = $quba->get_question(helpers::get_slot_number_by_uuid($question['uuid'], $quba));
        $question_type_class = get_class($question['question_object']->qtype);

        switch ($question_type_class) {
            case 'qtype_multichoice':
                $is_single = get_class($question['question_object']) == 'qtype_multichoice_single_question';
                $question['formatted_answer'] = self::format_multichoice_answer(
                    $question['answer'],
                    $is_single,
                    count($question['question_object']->answers)
                );
                break;
            default:
                throw new invalid_parameter_exception("Question type $question_type_class is not supported.");
        }

        $quba->process_action(
            helpers::get_slot_number_by_uuid($question['uuid'], $quba),
            $question['formatted_answer'],
            $time_at_request_start
        );
    }

    /**
     * Updates the completion status of the module.
     *
     * @param stdClass $module Moodle module instance.
     * @param int $user_id The user ID.
     * @return completion_info Completion information after update.
     * @throws moodle_exception If completion is not enabled for the module.
     */
    public static function update_module_completion(stdClass $module, int $user_id = 0): completion_info {
        $course = moodle_core::get_course($module->course);
        $completion = new completion_info($course);

        if ($completion->is_enabled($module)) {
            $completion->update_state($module, COMPLETION_COMPLETE, $user_id);
        } else {
            throw new moodle_exception('Completion is not enabled for this module.');
        }

        return $completion;
    }
}
