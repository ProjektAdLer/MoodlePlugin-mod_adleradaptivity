<?php

namespace mod_adleradaptivity\external;

use completion_info;
use context_module;
use local_adler\lib\adler_externallib_testcase;
use Mockery;
use mod_adleradaptivity\local\helpers;
use question_usage_by_activity;
use ReflectionProperty;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

class answer_questions_test extends adler_externallib_testcase {
    public function provide_test_execute_data() {
        return [
            'success with data' => [
                'element' => [
                    'module' => [
                        'module_id' => 1,
                    ],
                    'questions' => [[
                        'uuid' => 'uuid',
                        'answer' => "[false, false, true, false]",
                    ]]
                ],
                'expected_result' => [

                ],
                'expect_exception' => false,
                'task_exists' => true,
            ]
        ];
    }

    /**
     * @dataProvider provide_test_execute_data
     */
    public function test_execute($element, $expected_result, $expect_exception, $task_exists) {
        global $DB;

        // create mocks
        $context_module = Mockery::mock(context_module::class);
        $external_helpers = Mockery::mock('overload:' . external_helpers::class);
        $helpers = Mockery::mock('overload:' . helpers::class);
        $answer_questions = Mockery::mock(answer_questions::class)->shouldAllowMockingProtectedMethods()->makePartial();

        // inject context_module mock
        $context_module_reflected_property = new ReflectionProperty(answer_questions::class, 'context_module');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $context_module_reflected_property->setAccessible(true);
        $context_module_reflected_property->setValue($answer_questions, $context_module->mockery_getName());


        // mock validate_module_params_and_get_module
        $external_helpers->shouldReceive('validate_module_params_and_get_module')->once()->andReturn((object)['id' => 1, 'instance' => 1]);

        // mock context check
        $context_module->shouldReceive('instance')->once()->andReturn('context');
        $answer_questions->shouldReceive('validate_context')->once()->andReturn(1);

        // mock validate_and_enhance_questions
        $answer_questions->shouldReceive('validate_and_enhance_questions')->once()->andReturn(['questions']);

        // mock load_or_create_question_usage
        // first create fake mock question usage object
        $question_usage = Mockery::mock(question_usage_by_activity::class);
        // then mock load_or_create_question_usage
        $helpers->shouldReceive('load_or_create_question_usage')->once()->andReturn($question_usage);

        // mock process_questions
        // first create fake mock completion_info object
        $completion_info = Mockery::mock(completion_info::class);
        // then mock process_questions
        $answer_questions->shouldReceive('process_questions')->once()->andReturn($completion_info);

        // mock determine_module_completion_status
        $answer_questions->shouldReceive('determine_module_completion_status')->once()->andReturn('completion_status');

        // mock get_tasks_completion_data
        $answer_questions->shouldReceive('get_tasks_completion_data')->once()->andReturn([['uuid'=>'uuid', 'status'=>'status']]);

        // mock external_helpers::generate_question_response_data
        $external_helpers->shouldReceive('generate_question_response_data')->once()->andReturn([['uuid'=>'uuid', 'status'=>'status', 'answers'=>'answers']]);


        // call method to test
        $result = $answer_questions::execute($element['module'], $element['questions']);

        // pass result through response validation check
        $answer_questions->validate_parameters($answer_questions::execute_returns(), $result);


        // check result
        $this->assertEqualsCanonicalizing([
            'data' => [
                'module' => [
                    'module_id' => 1,
                    'instance_id' => 1,
                    'status' => 'completion_status',
                ],
                'tasks' => [['uuid'=>'uuid', 'status'=>'status']],
                'questions' => [['uuid'=>'uuid', 'status'=>'status', 'answers'=>'answers']],
            ]
        ], $result);


    }

}