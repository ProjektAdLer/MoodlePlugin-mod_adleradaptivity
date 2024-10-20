<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace mod_adleradaptivity\external;

use mod_adleradaptivity\lib\adler_externallib_testcase;
use mod_adleradaptivity\local\completion_helpers;
use moodle_exception;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

//require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/tests/generator/lib.php');


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
     *
     * # ANF-ID: [MVP3]
     */
    public function test_execute_integration(string $q1, string $q2, bool $task_required, bool $singlechoice, string $expected_result) {
        // create course with test questions and user
        $course_data = external_test_helpers::create_course_with_test_questions($this->getDataGenerator(), $task_required, $singlechoice, $q2 != 'none');

        // sign in as user
        $this->setUser($course_data['user']);

        // generate answer data
        $answerdata = external_test_helpers::generate_answer_question_parameters($q1, $q2, $course_data);


        // execute
        $result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // internal data format does not matter for api -> fixing this here
        $result = json_decode(json_encode($result), true);

        // execute return paramter validation
        answer_questions::validate_parameters(answer_questions::execute_returns(), $result);


        // validate
        $this->assertEquals($course_data['module']->cmid, $result['data']['module']['module_id']);
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

    public function provide_test_execute_integration__check_question_answered_correctly_once_data() {
        return [
            'successful 1' => [
                'attempt1' => 'correct',
                'attempt2' => 'incorrect',
                'expected_result' => completion_helpers::STATUS_CORRECT,
            ],
            'successful 2' => [
                'attempt1' => 'correct',
                'attempt2' => 'none',
                'expected_result' => completion_helpers::STATUS_CORRECT,
            ],
            'incorrect' => [
                'attempt1' => 'incorrect',
                'attempt2' => 'none',
                'expected_result' => completion_helpers::STATUS_INCORRECT,
            ],
        ];
    }

    /**
     * @dataProvider provide_test_execute_integration__check_question_answered_correctly_once_data
     *
     * # ANF-ID: [MVP3]
     */
    public function test_execute_integration__check_question_answered_correctly_once(string $attempt1, string $attempt2, $expected_result) {
        // create course with test questions and user
        $course_data = external_test_helpers::create_course_with_test_questions($this->getDataGenerator());

        // sign in as user
        $this->setUser($course_data['user']);

        // attempt 1
        // generate answer data
        $answerdata = external_test_helpers::generate_answer_question_parameters($attempt1, "null", $course_data);
        // execute
        $result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // attempt 2
        if ($attempt2 != 'none') {
            // generate answer data
            $answerdata = external_test_helpers::generate_answer_question_parameters($attempt2, "null", $course_data);
            // execute
            $result = answer_questions::execute($answerdata[0], $answerdata[1]);
        }

        // internal data format does not matter for api -> fixing this here
        $result = json_decode(json_encode($result), true);

        $this->assertEquals($expected_result, $result['data']['questions']['0']['status_best_try']);
    }
}
