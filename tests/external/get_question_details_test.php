<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace mod_adleradaptivity\external;

use mod_adleradaptivity\lib\adler_externallib_testcase;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

require_once($CFG->dirroot . '/question/tests/generator/lib.php');


/**
 * @runTestsInSeparateProcesses
 */
class get_question_details_test extends adler_externallib_testcase {
    /**
     * # ANF-ID: [MVP4]
     */
    public function test_execute_integration() {
        $task_required = true;
        $singlechoice = false;
        $q2 = false;
        $q1 = 'correct';

        // cheap way of creating test data
        // create course with test questions and user
        $course_data = external_test_helpers::create_course_with_test_questions($this->getDataGenerator(), $task_required, $singlechoice, $q2 != 'none');

        // sign in as user
        $this->setUser($course_data['user']);

        // generate answer data
        $answerdata = external_test_helpers::generate_answer_question_parameters($q1, $q2, $course_data);

        $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // data creation finish

        // execute get_question_details
        $result = get_question_details::execute(['module_id' => $course_data['module']->cmid]);

        // internal data format does not matter for api -> fixing this here
        $result = json_decode(json_encode($result), true);

        // execute return paramter validation
        answer_questions::validate_parameters(get_question_details::execute_returns(), $result);

        // verify result
        $this->assertEquals('correct', $result['data']['questions'][0]['status']);
        $this->assertEquals('notAttempted', $result['data']['questions'][1]['status']);
    }
}