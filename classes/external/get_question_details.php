<?php

namespace mod_adleradaptivity\external;

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');

use context_course;
use context_module;
use core_external\restricted_context_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use invalid_parameter_exception;

class get_question_details extends external_api {
    private static string $context_course = context_course::class;
    private static string $context_module = context_module::class;

    public static function execute_parameters(): external_function_parameters {
//        return new external_function_parameters(
//            [
//                'module_id' => new external_value(
//                    PARAM_TEXT,
//                    'Either module_id or instance_id are required. Module_id of the adaptivity module',
//                    VALUE_OPTIONAL
//                ),
//                'instance_id' => new external_value(
//                    PARAM_TEXT,
//                    'Either module_id or instance_id are required. Instance_id of the adaptivity module',
//                    VALUE_OPTIONAL
//                ),
//            ]
//        );
        return new external_function_parameters(
            array(
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
            )
        );
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'data' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'course_id' => new external_value(
                            PARAM_TEXT,
                            'course id (moodle id aka "instance id")',
                            VALUE_REQUIRED),
                        'element_type' => new external_value(
                            PARAM_TEXT,
                            'element type',
                            VALUE_REQUIRED),
                        'uuid' => new external_value(
                            PARAM_TEXT,
                            'element uuid',
                            VALUE_REQUIRED),

                        'moodle_id' => new external_value(
                            PARAM_INT,
                            'element moodle id',
                            VALUE_REQUIRED),
                        'context_id' => new external_value(
                            PARAM_INT,
                            'element context id, null for section',
                            VALUE_REQUIRED),
                    ),
                    'moodle ids and uuid for specified element type with given uuid'
                ),
            )
        ]);
    }

    /**
     * @param array $elements [int $course_id, string $element_type, array $uuids]
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws restricted_context_exception
     */
    public static function execute(array $elements): array {
//        // Parameter validation
//        $params = self::validate_parameters(self::execute_parameters(), array('elements' => $elements));
//        $elements = $params['elements'];
//
//        // for each uuid: check permissions and get moodle id and context id
//        $data = array();
//        foreach ($elements as $element) {
//            $course_id = $element['course_id'];
//            $element_type = $element['element_type'];
//            $uuid = $element['uuid'];
//
//            $moodle_id = null;
//            $context_id = null;
//            switch ($element_type) {
//                case 'section':
//                    try {
//                        $adler_section = section_db::get_adler_section_by_uuid($uuid, $course_id);
//                    } catch (dml_exception $e) {
//                        throw new invalid_parameter_exception('section not found, $uuid: ' . $uuid . ', $course_id: ' . $course_id);
//                    }
//                    $moodle_id = $adler_section->section_id;
//
//                    $moodle_section = section_db::get_moodle_section($moodle_id);
//                    $context = static::$context_course::instance($moodle_section->course);
////                    static::validate_context($context);  // TODO: check if user is enrolled (maybe validate context on course)
//
//                    // There is no context id for sections
//                    break;
//                case 'cm':
//                    try {
//                        $cm = cm_db::get_adler_course_module_by_uuid($uuid, $course_id);
//                    } catch (dml_exception $e) {
//                        throw new invalid_parameter_exception('course module not found, $uuid: ' . $uuid . ', $course_id: ' . $course_id);
//                    }
//                    $moodle_id = $cm->cmid;
//
//                    $context = static::$context_module::instance($moodle_id);
////                    static::validate_context($context); // TODO: check if user is enrolled
//
//                    $context_id = $context->id;
//                    break;
//                default:
//                    throw new invalid_parameter_exception('invalid element type ' . $element_type);
//            }
//            $data[] = [
//                'course_id' => $course_id,
//                'element_type' => $element_type,
//                'uuid' => $uuid,
//
//                'moodle_id' => $moodle_id,
//                'context_id' => $context_id,
//            ];
//        }
//
//        return ['data' => $data];
        return [''];
    }
}
