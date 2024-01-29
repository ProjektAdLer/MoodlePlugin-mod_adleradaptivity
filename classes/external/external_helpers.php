<?php

namespace mod_adleradaptivity\external;

use coding_exception;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_adleradaptivity\local\completion_helpers;
use mod_adleradaptivity\local\helpers;
use moodle_exception;
use question_usage_by_activity;
use stdClass;

class external_helpers {
    /**
     * Generate response data for a task
     *
     * @param question_usage_by_activity $quba The question usage object
     * @param stdClass $task The task object.
     * @return object with the fields uuid and status (one of correct, incorrect, notAttempted)
     * @throws moodle_exception
     */
    public static function generate_task_response_data(question_usage_by_activity $quba, stdClass $task): object {
        $status = completion_helpers::check_task_status($quba, $task);
        return (object) [
            'uuid' => $task->uuid,
            'status' => match ($status) {
                'correct' => 'correct',
                'incorrect', 'optional_incorrect' => 'incorrect',
                'notAttempted', 'optional_notAttempted' => 'notAttempted',
                default => throw new moodle_exception('invalid_parameter_exception', 'adleradaptivity', '', null, 'Invalid task status: ' . $status),
            },
        ];
    }

    /**
     * Validates that either module_id or instance_id are set in the parameters and returns the module
     *
     * @param array $module_params The parameters passed to the external function for the module field
     * @return stdClass The module object
     * @throws invalid_parameter_exception If neither module_id nor instance_id are set
     * @throws coding_exception If the module could not be found
     */
    public static function validate_module_params_and_get_module(array $module_params): stdClass {
        // $element has to contain either module_id or instance_id. Both are optional parameters as defined in execute_parameters.
        // - If module_id is given, fetch the module from the database and get the instance_id from it.
        // - If instance_id is given, fetch the module from the database and get the module_id from it.
        // - If none of them is given, throw an exception.
        if (isset($module_params['module_id'])) {
            $module = get_coursemodule_from_id('adleradaptivity', $module_params['module_id'], 0, false, MUST_EXIST);
        } else if (isset($module_params['instance_id'])) {
            $module = get_coursemodule_from_instance('adleradaptivity', $module_params['instance_id'], 0, false, MUST_EXIST);
        } else {
            throw new invalid_parameter_exception('Either module_id or instance_id has to be given.');
        }
        return $module;
    }

    /**
     * Generates request response data for questions
     *
     * @param array $question_uuids Array of questions. They have to be part of the $question_usage object.
     * @param question_usage_by_activity $question_usage The question usage object
     * @return array Array of questions with the fields uuid, status and answers
     * @throws moodle_exception If the question could not be found in the question usage object or answer checking failed.
     */
    public static function generate_question_response_data(array $question_uuids, question_usage_by_activity $question_usage): array {
        $response_data = [];

        foreach ($question_uuids as $question_uuid) {
            $question_attempt = $question_usage->get_question_attempt(helpers::get_slot_number_by_uuid($question_uuid, $question_usage));
            $status_last_try = completion_helpers::check_question_last_answer_correct($question_attempt);
            $status_last_try_text = match ($status_last_try) {
                true => 'correct',
                false => 'incorrect',
                null => 'notAttempted',
                default => throw new moodle_exception('invalid_parameter_exception', 'adleradaptivity', '', null, 'Invalid question status: ' . $status_last_try),
            };
            $status_best_try = completion_helpers::check_question_answered_correctly_once($question_attempt);
            $status_best_try_text = match ($status_best_try) {
                true => 'correct',
                false => 'incorrect',
                null => 'notAttempted',
                default => throw new moodle_exception('invalid_parameter_exception', 'adleradaptivity', '', null, 'Invalid question status: ' . $status_best_try),
            };

            $response_data[] = [
                'uuid' => $question_uuid,
                'status' => $status_last_try_text,
                'status_best_try' => $status_best_try_text,
                'answers' => json_encode(completion_helpers::get_question_answer_details($question_attempt)),
            ];
        }

        return $response_data;
    }

    public static function get_external_structure_question_response() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    "uuid" => new external_value(
                        PARAM_TEXT,
                        "UUID of the question"
                    ),
                    "status" => new external_value(
                        PARAM_TEXT,
                        "Status of the question, one of". api_constants::STATUS_CORRECT . ", " . api_constants::STATUS_INCORRECT . ", " . api_constants::STATUS_NOT_ATTEMPTED
                    ),
                    "status_best_try" => new external_value(
                        PARAM_TEXT,
                        "Status of the best try of the question, one of". api_constants::STATUS_CORRECT . ", " . api_constants::STATUS_INCORRECT . ", " . api_constants::STATUS_NOT_ATTEMPTED
                    ),
                    "answers" => new external_value(
                        PARAM_TEXT,
                        "JSON encoded data containing the question answer. For example for a multiple choice question: array of objects with the fields 'checked' and 'user_answer_correct'. null if the question was not attempted."
                    ),
                ]
            )
        );
    }
}