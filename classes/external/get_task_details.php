<?php


namespace mod_adleradaptivity\external;

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');

use core_external\restricted_context_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use invalid_parameter_exception;

class get_task_details extends external_api {
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
     * @param array $elements [int $course_id, string $element_type, array $uuids]
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws restricted_context_exception
     */
    public static function execute(array $element): array {
        // Parameter validation
        $params = self::validate_parameters(self::execute_parameters(), array('element' => $element));
        $element = $params['element'];

        return [
            'data' => [
                'tasks' => [
                    [
                        "uuid" => "298a7c8b-f6a6-41a7-b54f-065c70dc47c0",
                        "status" => "correct",
                    ],
                    [
                        "uuid" => "febcc2e5-c8b5-48c7-b1b7-e729e2bb12c3",
                        "status" => "incorrect",
                    ],
                    [
                        "uuid" => "687d3191-dc59-4142-a7cb-957049e50fcf ",
                        "status" => "notAttempted",
                    ],
                    [
                        "uuid" => "8b2d1cc2-e567-4558-aae5-55239deb3494",
                        "status" => "correct",
                    ]
                ]
            ]
        ];
    }
}
