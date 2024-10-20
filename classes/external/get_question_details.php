<?php

namespace mod_adleradaptivity\external;


use coding_exception;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;
use dml_exception;
use invalid_parameter_exception;
use mod_adleradaptivity\local\helpers;
use moodle_exception;

class get_question_details extends external_api {
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
                )
            ]
        );
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'data' => new external_single_structure(
                [
                    'questions' => external_helpers::get_external_structure_question_response()
                ]
            )
        ]);
    }

    /**
     * @param array $module [int $module_id, string $instance_id]
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     * @throws coding_exception If the module could not be found
     * @throws invalid_parameter_exception If neither module_id nor instance_id are set
     * @throws restricted_context_exception If the user does not have the required context to view the module
     */
    public static function execute(array $module): array {
        // Parameter validation
        $params = self::validate_parameters(self::execute_parameters(), array('module' => $module));
        $module = $params['module'];

        $module = external_helpers::validate_module_params_and_get_module($module);
        $module_id = $module->id;

        // default validation stuff with context
        $context = context_module::instance($module_id);
        static::validate_context($context);

        // load attempt
        $quba = helpers::load_or_create_question_usage($module_id);

        // load all questions in the attempt
        $question_uuids = [];
        foreach ($quba->get_slots() as $slot) {
            $question_uuids[] = $quba->get_question($slot)->idnumber;
        }

        // completion state of questions
        $questions_completion = external_helpers::generate_question_response_data($question_uuids, $quba);


        return [
            'data' => [
                'questions' => $questions_completion
            ]
        ];
    }
}
