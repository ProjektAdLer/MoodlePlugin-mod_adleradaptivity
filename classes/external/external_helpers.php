<?php

namespace mod_adleradaptivity\external;

use coding_exception;
use invalid_parameter_exception;
use mod_adleradaptivity\local\completion_helpers;
use mod_adleradaptivity\local\helpers;
use moodle_exception;
use question_usage_by_activity;
use stdClass;

class external_helpers {
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
     * @param array $questions Array of questions. They have to be part of the $question_usage object. Only the field uuid is required.
     * @param question_usage_by_activity $question_usage The question usage object
     * @return array Array of questions with the fields uuid, status and answers
     * @throws moodle_exception If the question could not be found in the question usage object or answer checking failed.
     */
    public static function generate_question_response_data(array $questions, question_usage_by_activity $question_usage): array {
        $response_data = [];

        foreach ($questions as $question) {
            $question_attempt = $question_usage->get_question_attempt(helpers::get_slot_number_by_uuid($question['uuid'], $question_usage));
            $response_data[] = [
                'uuid' => $question['uuid'],
                'status' => completion_helpers::check_question_correctly_answered($question_attempt) ? 'correct' : 'incorrect',
                'answers' => json_encode(completion_helpers::get_question_answer_details($question_attempt)),
            ];
        }

        return $response_data;
    }
}