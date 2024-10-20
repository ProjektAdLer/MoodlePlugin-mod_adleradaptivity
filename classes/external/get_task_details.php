<?php


namespace mod_adleradaptivity\external;


use coding_exception;
use context_module;
use core\di;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use dml_exception;
use invalid_parameter_exception;
use mod_adleradaptivity\local\db\adleradaptivity_task_repository;
use mod_adleradaptivity\local\helpers;
use moodle_exception;

class get_task_details extends external_api {
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
                    'tasks' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                "uuid" => new external_value(
                                    PARAM_TEXT,
                                    "UUID of the question"
                                ),
                                "status" => new external_value(
                                    PARAM_TEXT,
                                    "Status of the question, one of correct, incorrect, notAttempted"
                                ),
                            ]
                        )
                    )
                ]
            )
        ]);
    }

    /**
     * @param array $module
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function execute(array $module): array {
        $tasks_repo = di::get(adleradaptivity_task_repository::class);

        // Parameter validation
        $params = self::validate_parameters(self::execute_parameters(), array('module' => $module));
        $module = $params['module'];

        $module = external_helpers::validate_module_params_and_get_module($module);
        $module_id = $module->id;
        $instance_id = $module->instance;

        // default validation stuff with context
        $context = context_module::instance($module_id);
        static::validate_context($context);

        // load attempt
        $quba = helpers::load_or_create_question_usage($module_id);

        // load all tasks in current module
        $tasks = $tasks_repo->get_tasks_by_adleradaptivity_id($instance_id);

        // generate response data
        $results = [];
        foreach ($tasks as $task) {
            $results[] =external_helpers::generate_task_response_data($quba, $task);
        }


        return [
            'data' => [
                'tasks' => $results
            ]
        ];
    }
}
