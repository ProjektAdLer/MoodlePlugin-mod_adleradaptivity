<?php

namespace mod_adleradaptivity\external;

use completion_info;
use context_module;
use invalid_parameter_exception;
use local_adler\lib\adler_externallib_testcase;
use Mockery;
use mod_adleradaptivity\local\helpers;
use moodle_database;
use moodle_exception;
use question_bank;
use question_engine;
use question_usage_by_activity;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

require_once($CFG->dirroot . '/question/engine/tests/helpers.php');  // TODO: still required?
require_once($CFG->dirroot . '/question/tests/generator/lib.php');


/**
 * @runTestsInSeparateProcesses
 */
class answer_questions_test extends adler_externallib_testcase {
    public function provide_test_execute_integration_data() {
        return [
            'question correct' => [
                'q1' => 'correct',
                'q2' => 'none',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'correct',
            ],
            'question incorrect nothing chosen' => [
                'q1' => 'all_false',
                'q2' => 'none',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'incorrect',
            ],
            'question incorrect all chosen' => [
                'q1' => 'all_true',
                'q2' => 'none',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'incorrect',
            ],
            'question partially correct' => [
                'q1' => 'partially_correct',
                'q2' => 'none',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'incorrect',
            ],
            'question correct with 2nd unanswered question' => [
                'q1' => 'correct',
                'q2' => 'unanswered',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'correct',
            ],
            'question correct with 2nd answered question' => [
                'q1' => 'correct',
                'q2' => 'answered',
                'task_required' => true,
                'singlechoice' => false,
                'expected_result' => 'correct',
            ],
            'optional task incorrect answer' => [
                'q1' => 'incorrect',
                'q2' => 'none',
                'task_required' => false,
                'singlechoice' => false,
                'expected_result' => 'correct_question_wrong',
            ],
            'success singlechoioce' => [
                'q1' => 'correct',
                'q2' => 'none',
                'task_required' => true,
                'singlechoice' => true,
                'expected_result' => 'correct',
            ],
        ];
    }

    /**
     * @dataProvider provide_test_execute_integration_data
     */
    public function test_execute_integration(string $q1, string $q2, bool $task_required, bool $singlechoice, string $expected_result) {
        global $DB;

        $adleradaptivity_generator = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity');

        $uuid = '75c248df-562f-40f7-9819-ebbeb078954b';
        $uuid2 = '75c248df-562f-40f7-9819-ebbeb0789540';

        // create user, course and enrol user
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        // sign in as user
        $this->setUser($user);

        // create adleradaptivity module
        $adleradaptivity_module = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id, 'completion' => 2]);
        $adleradaptivity_task = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => $task_required ? 100 : null]);
        $adleradaptivity_task2 = $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_module->id, ['required_difficulty' => null, 'uuid' => 'uuid2', 'name' => 'task2']);
        $adleradaptivity_question = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id);
        if ($q2 != 'none') {
            $adleradaptivity_question2 = $adleradaptivity_generator->create_mod_adleradaptivity_question($adleradaptivity_task->id, ['difficulty' => 200]);
        }

        // create question
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $qcat1 = $generator->create_question_category(['name' => 'My category', 'sortorder' => 1, 'idnumber' => 'myqcat']);
        $q1_generated = $generator->create_question('multichoice', $singlechoice ? 'one_of_four' : null, ['name' => 'q1', 'category' => $qcat1->id, 'idnumber' => $uuid]);
        $question1 = question_bank::load_question($q1_generated->id);
        if ($q2 != 'none') {
            $q2_generated = $generator->create_question('multichoice', $singlechoice ? 'one_of_four' : null, ['name' => 'q2', 'category' => $qcat1->id, 'idnumber' => $uuid2]);
            $question2 = question_bank::load_question($q2_generated->id);
        }

        // patch q1 answers to give penalty if wrong
        $answers = $question1->answers;
        foreach ($answers as $answer) {
            $answer->fraction = $answer->fraction <= 0 ? -.5 : $answer->fraction;
            $DB->update_record('question_answers', $answer);
        }
        question_bank::notify_question_edited($question1->id);

        // create question reference
        $questionreferences = new stdClass();
        $questionreferences->usingcontextid = context_module::instance($adleradaptivity_module->cmid)->id;
        $questionreferences->component = 'mod_adleradaptivity';
        $questionreferences->questionarea = 'question';
        $questionreferences->itemid = $adleradaptivity_question->id;
        $questionreferences->questionbankentryid = $question1->questionbankentryid;
        $questionreferences->version = 1;
        $DB->insert_record('question_references', $questionreferences);
        if ($q2 != 'none') {
            $questionreferences2 = new stdClass();
            $questionreferences2->usingcontextid = context_module::instance($adleradaptivity_module->cmid)->id;
            $questionreferences2->component = 'mod_adleradaptivity';
            $questionreferences2->questionarea = 'question';
            $questionreferences2->itemid = $adleradaptivity_question2->id;
            $questionreferences2->questionbankentryid = $question2->questionbankentryid;
            $questionreferences2->version = 1;
            $DB->insert_record('question_references', $questionreferences2);
        }


        // generate answer data
        $answerdata_q1 = [];
        $partially_one_correct_chosen = false;
        foreach ($question1->answers as $answer) {
            switch ($q1) {
                case 'correct':
                    $answerdata_q1[] = $answer->fraction > 0;
                    break;
                case 'incorrect':
                    $answerdata_q1[] = $answer->fraction <= 0;
                    break;
                case 'all_true':
                    $answerdata_q1[] = true;
                    break;
                case 'all_false':
                    $answerdata_q1[] = false;
                    break;
                case 'partially_correct':
                    if ($partially_one_correct_chosen) {
                        $answerdata_q1[] = $answer->fraction == 0;
                    } else {
                        $answerdata_q1[] = $answer->fraction > 0;
                        $partially_one_correct_chosen = true;
                    }
                    break;
                default:
                    throw new moodle_exception('invalid_test_data', 'adleradaptivity');
            }
        }


        // generate parameters
        $param_module = [
            'instance_id' => $adleradaptivity_module->id,
        ];
        $param_questions = [
            [
                'uuid' => $uuid,
                'answer' => json_encode($answerdata_q1),
            ]
        ];
        if ($q2 == 'answered') {
            $param_questions[] = [
                'uuid' => $uuid2,
                'answer' => json_encode([true, false, false, false]),
            ];
        }

        // execute
        $result = answer_questions::execute($param_module, $param_questions);

        // internal data format does not matter for api -> fixing this here
        $result = json_decode(json_encode($result), true);

        // execute return paramter validation
        answer_questions::validate_parameters(answer_questions::execute_returns(), $result);


        // validate
        $this->assertEquals($adleradaptivity_module->cmid, $result['data']['module']['module_id']);
        if ($expected_result == 'correct') {
            $this->assertEquals('correct', $result['data']['module']['status']);
            $this->assertEquals('correct', $result['data']['tasks']['0']['status']);
            $this->assertEquals('correct', $result['data']['questions']['0']['status']);
        } else if ($expected_result == 'incorrect') {
            $this->assertEquals('incorrect', $result['data']['module']['status']);
            $this->assertEquals('incorrect', $result['data']['tasks']['0']['status']);
            $this->assertEquals('incorrect', $result['data']['questions']['0']['status']);
        } else if ($expected_result == 'correct_question_wrong') {
            $this->assertEquals('correct', $result['data']['module']['status']);
            $this->assertEquals('incorrect', $result['data']['tasks']['0']['status']);
            $this->assertEquals('incorrect', $result['data']['questions']['0']['status']);
        } else {
            throw new moodle_exception('invalid_test_data', 'adleradaptivity');
        }
    }

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
        $answer_questions->shouldReceive('get_tasks_completion_data')->once()->andReturn([['uuid' => 'uuid', 'status' => 'status']]);

        // mock external_helpers::generate_question_response_data
        $external_helpers->shouldReceive('generate_question_response_data')->once()->andReturn([['uuid' => 'uuid', 'status' => 'status', 'answers' => 'answers']]);


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
                'tasks' => [['uuid' => 'uuid', 'status' => 'status']],
                'questions' => [['uuid' => 'uuid', 'status' => 'status', 'answers' => 'answers']],
            ]
        ], $result);
    }

    public function provide_test_execute_data2() {
        return [
            'success with data' => [
                'element' => [
                    'module' => [
                        'module_id' => 1,
                    ],
                    'questions' => [[
                        'uuid' => 'uuid',
                        'answer' => "[false, false, true, false]",
                        'task' => (object)['uuid' => 'uuid']
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
     * @dataProvider provide_test_execute_data2
     */
    public function test_execute2($element, $expected_result, $expect_exception, $task_exists) {
        // create mocks
        $context_module = Mockery::mock(context_module::class);
        $completion_info = Mockery::mock(completion_info::class);
        $question_engine = Mockery::mock(question_engine::class);
        $external_helpers = Mockery::mock('overload:' . external_helpers::class);
        $helpers = Mockery::mock('overload:' . helpers::class);
        $question_usage = Mockery::mock(question_usage_by_activity::class);
        $answer_questions = Mockery::mock(answer_questions::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $DB = Mockery::mock(moodle_database::class);
        $transaction = Mockery::mock();

        // inject context_module mock
        $context_module_reflected_property = new ReflectionProperty(answer_questions::class, 'context_module');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $context_module_reflected_property->setAccessible(true);
        $context_module_reflected_property->setValue($answer_questions, $context_module->mockery_getName());

        // inject completion_info mock
        $completion_info_reflected_property = new ReflectionProperty(answer_questions::class, 'completion_info');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $completion_info_reflected_property->setAccessible(true);
        $completion_info_reflected_property->setValue($answer_questions, $completion_info->mockery_getName());

        // inject question_engine mock
        $question_engine_reflected_property = new ReflectionProperty(answer_questions::class, 'question_engine');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $question_engine_reflected_property->setAccessible(true);
        $question_engine_reflected_property->setValue($answer_questions, $question_engine->mockery_getName());

        // external_helpers mocks
        // mock validate_module_params_and_get_module
        $external_helpers->shouldReceive('validate_module_params_and_get_module')->once()->andReturn((object)['id' => 1, 'instance' => 1]);
        // mock get_task_by_question_uuid
        $external_helpers->shouldReceive('get_task_by_question_uuid')->once()->andReturn($element['questions']);

        // mock context check
        $context_module->shouldReceive('instance')->once()->andReturn('context');
        $answer_questions->shouldReceive('validate_context')->once()->andReturn(1);

        // mock load_or_create_question_usage
        $helpers->shouldReceive('load_or_create_question_usage')->once()->andReturn($question_usage);
        $helpers->shouldReceive('get_slot_number_by_uuid')->once()->andReturn(1);

        // mock DB calls
        $DB->shouldReceive('start_delegated_transaction')->once()->andReturn($transaction);
        $transaction->shouldReceive('allow_commit')->once();

        // mock question_engine calls
        $question_engine->shouldReceive('save_questions_usage_by_activity')->once();

        // mock question_usage calls
        $question_usage->shouldReceive('get_question')->once()->andReturn((object)['qtype' => new \qtype_multichoice_single_question()]);


    }
//
//        // Sample data
//        $questions = [
//            ['uuid' => 'valid-uuid-1'],
//            ['uuid' => 'valid-uuid-2']
//        ];
//        $task = new stdClass(); // Simulated task object
//        $external_helpers->shouldReceive('get_task_by_question_uuid')
//            ->times(count($questions))
//            ->andReturn($task);
//
//        // Reflection to access protected method
//        $reflector = new ReflectionClass(answer_questions::class);
//        $method = $reflector->getMethod('validate_and_enhance_questions');
//        $method->setAccessible(true);
//
//        // Execute the method
//        $enhancedQuestions = $method->invoke(null, $questions, 'instance-id');
//
//        // Assertions
//        foreach ($enhancedQuestions as $question) {
//            $this->assertArrayHasKey('task', $question);
//            $this->assertEquals($task, $question['task']);
//        }
//    }
//
//    public function testValidateAndEnhanceQuestionsInvalidUUID() {
//        // Mocking external_helpers
//        $external_helpers = Mockery::mock('alias:' . external_helpers::class);
//
//        $questions = [
//            ['uuid' => 'invalid-uuid']
//        ];
//
//        $external_helpers->shouldReceive('get_task_by_question_uuid')
//            ->once()
//            ->andThrow(new moodle_exception(""));
//
//        // Reflection to access protected method
//        $reflector = new ReflectionClass(answer_questions::class);
//        $method = $reflector->getMethod('validate_and_enhance_questions');
//        $method->setAccessible(true);
//
//        // Expect exception
//        $this->expectException(invalid_parameter_exception::class);
//
//        // Execute the method
//        $method->invoke(null, $questions, 'instance-id');
//    }
//
//    public function testDetermineModuleCompletionStatusCorrect() {
//        // Mock completion_info
//        $completion = Mockery::mock(completion_info::class);
//        $module = new stdClass();
//
//        // Simulate completion states that should result in STATUS_CORRECT
//        $completion->shouldReceive('get_data')
//            ->with($module)
//            ->andReturn((object)['completionstate' => COMPLETION_COMPLETE]);
//
//        // Reflection to access protected method
//        $reflector = new ReflectionClass(answer_questions::class);
//        $method = $reflector->getMethod('determine_module_completion_status');
//        $method->setAccessible(true);
//
//        // Execute the method and assert
//        $status = $method->invoke(null, $completion, $module);
//        $this->assertEquals(api_constants::STATUS_CORRECT, $status);
//
//        // Repeat for COMPLETION_COMPLETE_PASS
//        $completion->shouldReceive('get_data')
//            ->with($module)
//            ->andReturn((object)['completionstate' => COMPLETION_COMPLETE_PASS]);
//
//        $status = $method->invoke(null, $completion, $module);
//        $this->assertEquals(api_constants::STATUS_CORRECT, $status);
//    }
//
//    public function testDetermineModuleCompletionStatusIncorrect() {
//        // Mock completion_info
//        $completion = Mockery::mock(completion_info::class);
//        $module = new stdClass();
//
//        // Simulate a completion state that should result in STATUS_INCORRECT
//        $completion->shouldReceive('get_data')
//            ->with($module)
//            ->andReturn((object)['completionstate' => COMPLETION_INCOMPLETE]);
//
//        // Reflection to access protected method
//        $reflector = new ReflectionClass(answer_questions::class);
//        $method = $reflector->getMethod('determine_module_completion_status');
//        $method->setAccessible(true);
//
//        // Execute the method and assert
//        $status = $method->invoke(null, $completion, $module);
//        $this->assertEquals(api_constants::STATUS_INCORRECT, $status);
//    }

    public function provideTestValidateAndEnhanceQuestionsData() {
        return [
            'all questions valid' => [
                'questions' => [
                    ['uuid' => 'valid-uuid-1'],
                    ['uuid' => 'valid-uuid-2']
                ],
                'instance_id' => 'instance-id',
                'expect_exception' => false,
            ],
            'question not valid' => [
                'questions' => [
                    ['uuid' => 'invalid-uuid'],
                ],
                'instance_id' => 'instance-id',
                'expect_exception' => true,
            ]
        ];
    }

    /**
     * @dataProvider provideTestValidateAndEnhanceQuestionsData
     */
    public function testValidateAndEnhanceQuestions($questions, $instance_id, $expect_exception) {
        $external_helpers = Mockery::mock('alias:' . external_helpers::class);
        $task = new stdClass(); // Simulated task object

        if (!$expect_exception) {
            $external_helpers->shouldReceive('get_task_by_question_uuid')
                ->times(count($questions))
                ->andReturn($task);
        } else {
            $external_helpers->shouldReceive('get_task_by_question_uuid')
                ->andThrow(new moodle_exception(''));
        }

        $reflector = new ReflectionClass(answer_questions::class);
        $method = $reflector->getMethod('validate_and_enhance_questions');
        $method->setAccessible(true);

        if ($expect_exception) {
            $this->expectException(invalid_parameter_exception::class);
        }

        $enhancedQuestions = $method->invoke(null, $questions, $instance_id);

        if (!$expect_exception) {
            foreach ($enhancedQuestions as $question) {
                $this->assertArrayHasKey('task', $question);
                $this->assertEquals($task, $question['task']);
            }
        }
    }

    public function provideTestDetermineModuleCompletionStatusData() {
        return [
            'completion complete' => [
                'completionState' => COMPLETION_COMPLETE,
                'expectedStatus' => api_constants::STATUS_CORRECT,
            ],
            'completion complete pass' => [
                'completionState' => COMPLETION_COMPLETE_PASS,
                'expectedStatus' => api_constants::STATUS_CORRECT,
            ],
            'completion incomplete' => [
                'completionState' => COMPLETION_INCOMPLETE,
                'expectedStatus' => api_constants::STATUS_INCORRECT,
            ]
        ];
    }

    /**
     * @dataProvider provideTestDetermineModuleCompletionStatusData
     */
    public function testDetermineModuleCompletionStatus($completionState, $expectedStatus) {
        $completion = Mockery::mock(completion_info::class);
        $module = new stdClass();
        $module->id = 123; // Example module ID

        $completion->shouldReceive('get_data')
            ->with($module)
            ->andReturn((object)['completionstate' => $completionState]);

        $reflector = new ReflectionClass(answer_questions::class);
        $method = $reflector->getMethod('determine_module_completion_status');
        $method->setAccessible(true);

        $status = $method->invoke(null, $completion, $module);

        $this->assertEquals($expectedStatus, $status);
    }

    public function provideTestGetTasksCompletionData() {
        return [
            'unique tasks' => [
                'questions' => [
                    ['task' => (object)['uuid' => 'uuid1']],
                    ['task' => (object)['uuid' => 'uuid2']]
                ],
                'expectedTasksCount' => 2,
            ],
            'duplicate tasks' => [
                'questions' => [
                    ['task' => (object)['uuid' => 'uuid1']],
                    ['task' => (object)['uuid' => 'uuid1']]
                ],
                'expectedTasksCount' => 1,
            ]
        ];
    }

    /**
     * @dataProvider provideTestGetTasksCompletionData
     */
    public function testGetTasksCompletionData($questions, $expectedTasksCount) {
        $external_helpers = Mockery::mock('alias:' . external_helpers::class);
        $quba = Mockery::mock(question_usage_by_activity::class);

        foreach ($questions as $question) {
            $taskResponseData = ['uuid' => $question['task']->uuid, 'someData' => 'value'];
            $external_helpers->shouldReceive('generate_task_response_data')
                ->with($quba, $question['task'])
                ->andReturn($taskResponseData);
        }

        $reflector = new ReflectionClass(answer_questions::class);
        $method = $reflector->getMethod('get_tasks_completion_data');
        $method->setAccessible(true);

        $tasks = $method->invoke(null, $questions, $quba);

        $this->assertCount($expectedTasksCount, $tasks);
        foreach ($tasks as $task) {
            $this->assertArrayHasKey('uuid', $task);
            $this->assertArrayHasKey('someData', $task);
        }
    }

    public function testProcessQuestions() {
        // Mock dependencies
        $DB = Mockery::mock(moodle_database::class);
        $quba = Mockery::mock(question_usage_by_activity::class);
        $module = (object)['course' => 1, 'id' => 1]; // Example module data
        $reflector = new ReflectionClass(answer_questions::class);

        // Set up questions
        $questions = [
            ['uuid' => 'uuid1', 'answer' => json_encode([true, false])],
            // Additional questions can be added here
        ];

        // Mock transaction
        $transaction = Mockery::mock();
        $transaction->shouldReceive('allow_commit');
        $DB->shouldReceive('start_delegated_transaction')->andReturn($transaction);

        // Mock question_usage_by_activity
        $quba->shouldReceive('get_question')->andReturnUsing(function ($slot) {
            $question = new stdClass();
            $question->qtype = new stdClass();
            return $question;
        });
        $quba->shouldReceive('process_action');

        // Mock static calls
        Mockery::mock('alias:helpers')
            ->shouldReceive('get_slot_number_by_uuid')->andReturn(1); // Example slot number

        $question_engine = Mockery::mock(question_engine::class);

        $question_engine_reflected_property = new ReflectionProperty(answer_questions::class, 'question_engine');
        $question_engine_reflected_property->setAccessible(true);
        $question_engine_reflected_property->setValue($reflector, $question_engine->mockery_getName());

        $question_engine->shouldReceive('save_questions_usage_by_activity');

        // Mock completion_info and related calls
        Mockery::mock('alias:moodle_core')
            ->shouldReceive('get_course')->andReturn(new stdClass());

        $completion = Mockery::mock(completion_info::class);
        $completion->shouldReceive('is_enabled')->andReturn(true);
        $completion->shouldReceive('update_state');

        // Mock constructor of completion_info
        $question_engine = Mockery::mock(completion_info::class);

        $question_engine_reflected_property = new ReflectionProperty(answer_questions::class, 'completion_info');
        $question_engine_reflected_property->setAccessible(true);
        $question_engine_reflected_property->setValue($reflector, $question_engine->mockery_getName());

        $question_engine->shouldReceive('__construct')->andReturn($completion);

        // Execute the method
        $method = $reflector->getMethod('process_questions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $questions, 123456789, $module, $DB, $quba); // 123456789 is example time_at_request_start

        // Assertions
        $this->assertInstanceOf(completion_info::class, $result);
        // Additional assertions can be added for validating the state of $questions, $module, etc.
    }
}
