<?php

namespace mod_adleradaptivity\external;

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');

use context_module;
use core_external\restricted_context_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use invalid_parameter_exception;
use mod_adleradaptivity\local\helpers;
use moodle_exception;

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
                    'modules' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                "uuid" => new external_value(
                                    PARAM_TEXT,
                                    "UUID of the module"
                                ),
                                "status" => new external_value(
                                    PARAM_TEXT,
                                    "Status of the Task, one of correct, incorrect"
                                ),
                            ]
                        )
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
                                "status_question" => new external_value(
                                    PARAM_TEXT,
                                    "Status of the Task, one of correct, incorrect, notAttempted"
                                ),
                                "answers" => new external_value(
                                    PARAM_TEXT,
                                    "JSON encoded data containing the question answer. For example for a multiple choice question: array of objects with the fields 'checked' and 'answer_correct'. null if the question was not attempted."
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
        foreach($questions as $key => $question) {
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

        // TODO: this should work with class loading (or not because class loading is for classes)
//        global $CFG;
//        require_once($CFG->dirroot . '/mod/adleradaptivity/classes/local/helpers.php');
        // load attempt
        $quba = helpers::load_or_create_question_usage($module_id);


        // TODO: question type handling: if question is of type "multichoice", then continue, otherwise return not supported
        // TODO: convert $question['answer'] ([false, false, true, false]) to the format required by the question type
        // TODO: Proccess answer
        //    create attempt if not exists, otherwise load attempt

        // TODO: after processing all questions: check if affected tasks are now complete
        // TODO: after processing all questions: check if module is now complete

        // TODO: return data with status of affected questions, tasks and modules



        return [
            'data' => [
                'tasks' => [
                    [
                        'uuid' => '687d3191-dc59-4142-a7cb-957049e50fcf',
                        'status' => 'correct', // or incorrect
                    ]
                ],
                'modules' => [
                    [
                        'uuid' => '687d3191-dc59-4142-a7cb-957049e50fcf',
                        'status' => 'correct', // or incorrect
                    ]
                ],
                'questions' => [
                    [
                        "uuid" => "298a7c8b-f6a6-41a7-b54f-065c70dc47c0",
                        "status_question" => "correct", // or incorrect
                        "answers" => json_encode([
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => true, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                        ])
                    ],
                    [
                        "uuid" => "febcc2e5-c8b5-48c7-b1b7-e729e2bb12c3",
                        "status_question" => "incorrect",
                        "answers" => json_encode([
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => false],
                            ['checked' => true, 'answer_correct' => false],
                            ['checked' => false, 'answer_correct' => false],
                        ])
                    ],
                    [
                        "uuid" => "687d3191-dc59-4142-a7cb-957049e50fcf ",
                        "status_question" => "correct",
                        "answers" => json_encode([
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => true, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                        ])
                    ],
                    [
                        "uuid" => "8b2d1cc2-e567-4558-aae5-55239deb3494",
                        "status_question" => "correct",
                        "answers" => json_encode([
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                            ['checked' => true, 'answer_correct' => true],
                            ['checked' => false, 'answer_correct' => true],
                        ])
                    ]
                ]
            ]
        ];
    }
}
