<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace mod_adleradaptivity\local\output\pages;

use context_module;
use Mockery;
use mod_adleradaptivity\lib\adler_testcase;
use mod_adleradaptivity\moodle_core;
use moodle_exception;
use question_engine;
use ReflectionClass;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

class view_page_test extends adler_testcase {
    public function provider_test_request_parameters(): array {
        return [
            'null' => [null],
            '1' => [1],
            '7' => [7],
            'string "9"' => ['9'],
        ];
    }


    /**
     * @dataProvider provider_test_request_parameters
     *
     * ANF-ID: [MVP14]
     */
    public function testProcessRequestParameters($attempt_id) {
        // Arrange
        $_GET['attempt'] = $attempt_id; // positive value for attempt
        $_GET['id'] = 1; // positive value for cmid
        $mockAdleradaptivityRepository = Mockery::mock('mod_adleradaptivity\local\db\adleradaptivity_repository');
        $mockAdleradaptivityRepository->shouldReceive('get_instance_by_instance_id')
            ->andReturn((object)['instance' => 1]);

        $viewPage = Mockery::mock(view_page::class)->makePartial()->shouldAllowMockingProtectedMethods();

        // Mock MoodleCore functions
        $mockMoodleCore = Mockery::mock('alias:' . moodle_core::class);
        $mockMoodleCore->shouldReceive('get_coursemodule_from_id')
            ->andReturn((object)['course' => 1, 'instance' => 1]);
        $mockMoodleCore->shouldReceive('get_course')
            ->andReturn((object)['fullname' => 'Test Course']);

        // Use reflection to set the private property
        $reflection = new ReflectionClass(view_page::class);
        $property = $reflection->getProperty('adleradaptivity_repository');
        $property->setAccessible(true);
        $property->setValue($viewPage, $mockAdleradaptivityRepository);

        // Use reflection to call the private method
        $method = $reflection->getMethod('process_request_parameters');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($viewPage);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertEquals($attempt_id, $result[0]); // attempt_id
        $this->assertEquals(1, $result[1]->instance); // cm
        $this->assertEquals('Test Course', $result[2]->fullname); // course
        $this->assertEquals(1, $result[3]->instance); // module_instance
    }

    public function invalidAttemptProvider(): array {
        return [
            '-7' => [-7],
            'abc' => ['abc'],
            '7.4' => [7.4],
        ];
    }

    /**
     * @dataProvider invalidAttemptProvider
     *
     * ANF-ID: [MVP14]
     */
    public function testProcessRequestParametersWithInvalidAttempt($invalidAttempt) {
        // Arrange
        $_GET['attempt'] = $invalidAttempt;
        $_GET['id'] = 1; // positive value for cmid
        $mockAdleradaptivityRepository = Mockery::mock('mod_adleradaptivity\local\db\adleradaptivity_repository');
        $mockAdleradaptivityRepository->shouldReceive('get_instance_by_instance_id')
            ->andReturn((object)['instance' => 1]);

        $viewPage = Mockery::mock(view_page::class)->makePartial()->shouldAllowMockingProtectedMethods();

        // Mock MoodleCore functions
        $mockMoodleCore = Mockery::mock('alias:' . moodle_core::class);
        $mockMoodleCore->shouldReceive('get_coursemodule_from_id')
            ->andReturn((object)['course' => 1, 'instance' => 1]);
        $mockMoodleCore->shouldReceive('get_course')
            ->andReturn((object)['fullname' => 'Test Course']);

        // Use reflection to set the private property
        $reflection = new ReflectionClass(view_page::class);
        $property = $reflection->getProperty('adleradaptivity_repository');
        $property->setAccessible(true);
        $property->setValue($viewPage, $mockAdleradaptivityRepository);

        // Use reflection to call the private method
        $method = $reflection->getMethod('process_request_parameters');
        $method->setAccessible(true);

        // Assert
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('invalidattemptid');

        // Act
        $method->invoke($viewPage);
    }

    /**
     * @runInSeparateProcess
     *
     *  ANF-ID: [MVP14]
     */
    public function testProcessRequestParametersWithNonExistingAttempt() {
        // create two users
        $user1 = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the users in the course
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        // create adler adaptivity instance
        $adleradaptivity = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id]);

        // create an attempt
        // first create a moodle attempt
        $module_context = context_module::instance($adleradaptivity->cmid);
        $moodle_attempt = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $module_context);
        $moodle_attempt->set_preferred_behaviour(1);
        question_engine::save_questions_usage_by_activity($moodle_attempt);
        // then create an adleradaptivity attempt
        $attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')->create_mod_adleradaptivity_attempt($moodle_attempt->get_id(), $user1->id);

        // login as user1
        $this->setUser($user1);

        // Arrange
        $_GET['id'] = $adleradaptivity->cmid;
        // Access non existent attempt
        $_GET['attempt'] = $moodle_attempt->get_id() + 1;

        // expect exception
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('invalidattemptid');

        // Load the page to test
        new view_page();
    }

    /**
     * @runInSeparateProcess
     *
     *  ANF-ID: [MVP14]
     */
    public function testProcessRequestParametersWithAttemptOfOtherUser() {
        // create two users
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the users in the course
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);

        // create adler adaptivity instance
        $adleradaptivity = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id]);

        // create an attempt
        // first create a moodle attempt
        $module_context = context_module::instance($adleradaptivity->cmid);
        $moodle_attempt = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $module_context);
        $moodle_attempt->set_preferred_behaviour(1);
        question_engine::save_questions_usage_by_activity($moodle_attempt);
        // then create an adleradaptivity attempt
        $attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')->create_mod_adleradaptivity_attempt($moodle_attempt->get_id(), $user1->id);

        // login as user2
        $this->setUser($user2);

        // Arrange
        $_GET['id'] = $adleradaptivity->cmid;
        // Access non existent attempt
        $_GET['attempt'] = $moodle_attempt->get_id();

        // expect exception
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('do not currently have permissions');

        // Load the page to test
        new view_page();
    }

    /**
     * @runInSeparateProcess
     *
     *  ANF-ID: [MVP14]
     */
    public function testProcessRequestParametersWithAttemptOfOtherCm() {
        // create two users
        $user1 = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the users in the course
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        // create adler adaptivity instance
        $adleradaptivity = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id]);
        $adleradaptivity2 = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id]);

        // create an attempt
        // first create a moodle attempt
        $module_context = context_module::instance($adleradaptivity->cmid);
        $moodle_attempt = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $module_context);
        $moodle_attempt->set_preferred_behaviour(1);
        question_engine::save_questions_usage_by_activity($moodle_attempt);
        // then create an adleradaptivity attempt
        $attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')->create_mod_adleradaptivity_attempt($moodle_attempt->get_id(), $user1->id);

        // login as user2
        $this->setUser($user1);

        // Arrange
        $_GET['id'] = $adleradaptivity2->cmid;
        // Access non existent attempt
        $_GET['attempt'] = $moodle_attempt->get_id();

        // expect exception
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('invalidattemptid');

        // Load the page to test
        new view_page();
    }
}