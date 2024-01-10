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
                'expected_result' => 'correct',
            ],
            'question incorrect nothing chosen' => [
                'q1' => 'all_false',
                'q2' => 'none',
                'task_required' => true,
                'expected_result' => 'incorrect',
            ],
            'question incorrect all chosen' => [
                'q1' => 'all_true',
                'q2' => 'none',
                'task_required' => true,
                'expected_result' => 'incorrect',
            ],
            'question partially correct' => [
                'q1' => 'partially_correct',
                'q2' => 'none',
                'task_required' => true,
                'expected_result' => 'incorrect',
            ],
            'question correct with 2nd unanswered question' => [
                'q1' => 'correct',
                'q2' => 'unanswered',
                'task_required' => true,
                'expected_result' => 'correct',
            ],
            'question correct with 2nd answered question' => [
                'q1' => 'correct',
                'q2' => 'answered',
                'task_required' => true,
                'expected_result' => 'correct',
            ],
            'optional task incorrect answer' => [
                'q1' => 'incorrect',
                'q2' => 'none',
                'task_required' => false,
                'expected_result' => 'correct_question_wrong',
            ],
        ];
    }

    /**
     * @dataProvider provide_test_execute_integration_data
     */
    public function test_execute_integration(string $q1, string $q2, bool $task_required, string $expected_result) {
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
        $q1_generated = $generator->create_question('multichoice', null, ['name' => 'q1', 'category' => $qcat1->id, 'idnumber' => $uuid]);
        $question1 = question_bank::load_question($q1_generated->id);
        if ($q2 != 'none') {
            $q2_generated = $generator->create_question('multichoice', null, ['name' => 'q2', 'category' => $qcat1->id, 'idnumber' => $uuid2]);
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

}
