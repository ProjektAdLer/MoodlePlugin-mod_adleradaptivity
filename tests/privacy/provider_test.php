<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace mod_adleradaptivity\privacy;

use coding_exception;
use context_module;
use core\context\module;
use core\di;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\request\approved_contextlist;
use core_user;
use Exception;
use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\lib\adler_testcase;
use moodle_database;
use question_engine;
use stdClass;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

class provider_test extends adler_testcase {
    private stdClass $user;
    private stdClass $other_user;
    private module $module_context;
    private stdClass $module;
    private $q1;

    public function setUp(): void {
        parent::setUp();

        $plugin_generator = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity');

        // set up testcourse
        $course_data = $plugin_generator->create_course_with_test_questions($this->getDataGenerator());
        $this->other_user = $this->getDataGenerator()->create_user();

        // set up variables
        $this->user = $course_data['user'];
        $this->q1 = $course_data['q1'];
        $this->module = $course_data['module'];
        $course = $course_data['course'];
        $this->module_context = context_module::instance($this->module->cmid);

        // Sign user in
        $this->setUser($this->user);

        // create an attempt
        // Generate answer data.
        $answerdata = $plugin_generator->generate_answer_question_parameters(
            'correct',
            false,
            $course_data
        );
        // Send request
        $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);
    }

    public function test_get_contents_for_userid_no_data() {
        // Fetch the contexts - no contexts should be returned.
        $contextlist = provider::get_contexts_for_userid($this->other_user->id);
        $this->assertCount(0, $contextlist);
    }

    public function test_get_contexts_for_userid() {
        // Fetch the contexts - only one context should be returned.
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($this->module_context, $contextlist->current());
    }

    private function export_user_data(int $user_id) {
        $contextlist = provider::get_contexts_for_userid($user_id);

        // Perform the export and check the data.
        $approvedcontextlist = new approved_contextlist(
            core_user::get_user($user_id),
            'mod_adleradaptivity',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);
    }

    public function test_export_user_data_activity() {
        $this->export_user_data($this->user->id);

        // Verify activity export
        $this->assertTrue(writer::with_context($this->module_context)->has_any_data());

        $activity_data = writer::with_context($this->module_context)->get_data([]);
        $this->assertEquals($this->module->name, $activity_data->name);
        $this->assertEquals($this->module->intro, $activity_data->intro);
        $this->assertEquals(get_string('privacy:export:attempt:completed', 'mod_adleradaptivity'), $activity_data->completion);
    }

    public function test_export_user_data_attempt() {
        $this->export_user_data($this->user->id);

        // Verify attempt export
        $attemptsubcontext = [
            get_string('privacy:export:attempt', 'mod_adleradaptivity'),
            1
        ];
        $attemptdata = writer::with_context($this->module_context)->get_data($attemptsubcontext);

        $this->assertTrue(isset($attemptdata->state));
        $this->assertEquals(get_string('privacy:export:attempt:completed', 'mod_adleradaptivity'), $attemptdata->state);
    }

    public function test_export_user_data_attempt_questions() {
        $this->export_user_data($this->user->id);

        // Verify attempt question export
        $question_subcontext = [
            get_string('privacy:export:attempt', 'mod_adleradaptivity'),
            1,
            get_string('questions', 'core_question')];
        $this->assertEquals(di::get(moodle_database::class)->count_records('adleradaptivity_attempts'), 1);
        $attempt_id = di::get(moodle_database::class)->get_field('adleradaptivity_attempts', 'attempt_id', []);
        $quba = question_engine::load_questions_usage_by_activity((int)$attempt_id);
        foreach ($quba->get_slots() as $slotno) {
            $question_attempt = $quba->get_question_attempt($slotno);

            $exported_question = writer::with_context($this->module_context)->get_data(array_merge($question_subcontext, [$slotno]));
            $exported_question->name = $this->q1['question']->name;
            $this->assertEquals($question_attempt->get_response_summary(), $exported_question->answer);
        }
    }

    public function test_delete_data_for_all_users_in_context() {
        // Create second module
        $module2 = $this->getDataGenerator()->create_module('adleradaptivity', [
            'course' => $this->module->course,
        ]);
        $attempt2 = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')->create_mod_adleradaptivity_attempt(
            $module2->cmid,
            $this->user->id,
            $module2->id
        );

        provider::delete_data_for_all_users_in_context($this->module_context);

        $this->assertEquals(1, di::get(moodle_database::class)->count_records('adleradaptivity_attempts'));
        $this->assertEquals(0, di::get(moodle_database::class)->count_records('adleradaptivity_attempts', ['adleradaptivity_id' => $this->module->id]));
    }

    public function test_delete_data_for_user() {
        $db = di::get(moodle_database::class);
        $attempt_id = $db->get_field('adleradaptivity_attempts', 'attempt_id', ['user_id' => $this->user->id]);


        // Create a second user and attempt
        $other_attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')
            ->create_mod_adleradaptivity_attempt(
                $this->module->cmid,
                $this->other_user->id,
                $this->module->id
            );

        // Initial state check
        $this->assertEquals(2, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));
        question_engine::load_questions_usage_by_activity($attempt_id);

        // Delete data for main test user
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $approvedcontextlist = new approved_contextlist(
            core_user::get_user($this->user->id),
            'mod_adleradaptivity',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Verify that only the test user's attempt was deleted
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(0, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));
        try {
            question_engine::load_questions_usage_by_activity($attempt_id);
        } catch (Exception $e) {
            $this->assertStringContainsString(coding_exception::class, get_class($e));
        }
    }

    public function test_delete_data_for_users() {
        $db = di::get(moodle_database::class);

        // Create a second user and attempt
        $other_attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')
            ->create_mod_adleradaptivity_attempt(
                $this->module->cmid,
                $this->other_user->id,
                $this->module->id
            );

        // Initial state check
        $this->assertEquals(2, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));

        // Delete data for both users
        $userlist = new approved_userlist($this->module_context, 'mod_adleradaptivity', [$this->user->id, $this->other_user->id]);
        provider::delete_data_for_users($userlist);

        // Verify that all attempts were deleted
        $this->assertEquals(0, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(0, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(0, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));

        // Verify that the question usage was deleted for both users
        $attempt_id = $db->get_field('adleradaptivity_attempts', 'attempt_id', ['user_id' => $this->user->id]);
        try {
            question_engine::load_questions_usage_by_activity($attempt_id);
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertStringContainsString(coding_exception::class, get_class($e));
        }

        try {
            question_engine::load_questions_usage_by_activity($other_attempt->attempt_id);
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertStringContainsString(coding_exception::class, get_class($e));
        }
    }

    public function test_delete_data_for_single_user_from_multiple() {
        $db = di::get(moodle_database::class);

        // Create a second user and attempt
        $other_attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')
            ->create_mod_adleradaptivity_attempt(
                $this->module->cmid,
                $this->other_user->id,
                $this->module->id
            );

        // Initial state check
        $this->assertEquals(2, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));

        // Delete data for only the main test user
        $userlist = new approved_userlist($this->module_context, 'mod_adleradaptivity', [$this->user->id]);
        provider::delete_data_for_users($userlist);

        // Verify that only the main test user's attempt was deleted
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts'));
        $this->assertEquals(0, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->user->id]));
        $this->assertEquals(1, $db->count_records('adleradaptivity_attempts',
            ['user_id' => $this->other_user->id]));

        // Verify that the question usage was deleted for the main test user
        $attempt_id = $db->get_field('adleradaptivity_attempts', 'attempt_id', ['user_id' => $this->user->id]);
        try {
            question_engine::load_questions_usage_by_activity($attempt_id);
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertStringContainsString(coding_exception::class, get_class($e));
        }

        // Verify that the attempt was not deleted for the other user. question_usage can not be tested as the
        // simplified attempt created here with create_mod_adleradaptivity_attempt does not create an actual attempt
        $db->get_field('adleradaptivity_attempts', 'attempt_id', ['user_id' => $this->other_user->id]);
    }
}