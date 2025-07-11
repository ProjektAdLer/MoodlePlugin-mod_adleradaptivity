<?php

use core\di;
use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\lib\adler_testcase;
use mod_adleradaptivity\local\db\adleradaptivity_question_repository;
use mod_adleradaptivity\local\db\adleradaptivity_repository;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

class lib_test extends adler_testcase {
    private $plugin_generator;

    public function setUp(): void {
        parent::setUp();
        $this->plugin_generator = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity');
    }

    public function test_add_instance() {
        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $this->assertCount(0, $DB->get_records('adleradaptivity'));

        $adleradaptivity_module = (object)[
            'name' => 'name',
            'intro' => 'intro',
            'adaptivity_element_intro' => 'adaptivity_element_intro',
            'course' => $course->id,
            'coursemodule' => 0,
        ];

        adleradaptivity_add_instance($adleradaptivity_module);

        $this->assertCount(1, $DB->get_records('adleradaptivity'));
    }

    public function test_update_instance() {
        // Arrange
        $moduleinstance = new stdClass();

        // Act and Assert
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('update_instance() is not supported');
        adleradaptivity_update_instance($moduleinstance, null);
    }

    public function test_delete_instance() {
        global $DB;
        $generator = $this->getDataGenerator();

        // Create a course.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $adleradaptivity_module1 = $generator->create_module('adleradaptivity', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC]);
        $adleradaptivity_module2 = $generator->create_module('adleradaptivity', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC]);


        // Ensure that the instances were created.
        $this->assertCount(2, $DB->get_records('adleradaptivity'));

        // Delete the first instance.
        $result = adleradaptivity_delete_instance($adleradaptivity_module1->id);

        // Ensure that the deletion was successful.
        $this->assertTrue($result);

        // Ensure that the instance was deleted.
        $this->assertCount(1, $DB->get_records('adleradaptivity'));

        // Ensure that the second instance still exists.
        $remainingInstance = $DB->get_record('adleradaptivity', ['id' => $adleradaptivity_module2->id]);
        $this->assertNotEmpty($remainingInstance);
    }

    /**
     * Data provider for test_delete_complex_instance.
     */
    public static function data_provider_for_test_delete_complex_instance() {
        return [
            'Test case 1: Without attempt' => [false],
            'Test case 2: With attempt' => [true],
        ];
    }

    /**
     * @dataProvider data_provider_for_test_delete_complex_instance
     */
    public function test_delete_complex_instance($withAttempt) {
        global $DB;
        $generator = $this->getDataGenerator();

        // Create a complex module instance with test questions.
        $complex_adleradaptivity_module = $this->plugin_generator->create_course_with_test_questions($generator);

        // Create a second, trivial module instance.
        $trivial_adleradaptivity_module = $generator->create_module('adleradaptivity', ['course' => $complex_adleradaptivity_module['course']->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC]);

        // If withAttempt is true, create an attempt.
        if ($withAttempt) {
            // Sign in as user.
            $this->setUser($complex_adleradaptivity_module['user']);

            // Generate answer data.
            $answerdata = $this->plugin_generator->generate_answer_question_parameters('correct', false, $complex_adleradaptivity_module);

            // Create an attempt.
            $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);
        }

        // Ensure that the instances were created.
        $this->assertCount(2, $DB->get_records('adleradaptivity'));

        // Delete the complex instance.
        $result = adleradaptivity_delete_instance($complex_adleradaptivity_module['module']->id);

        // Ensure that the deletion was successful.
        $this->assertTrue($result);
        $this->assertCount(1, $DB->get_records('adleradaptivity'));
        $this->assertCount(0, $DB->get_records('adleradaptivity_questions'));
        $this->assertCount(0, $DB->get_records('adleradaptivity_tasks'));

        // Ensure that the trivial instance still exists.
        $remainingInstance = $DB->get_record('adleradaptivity', ['id' => $trivial_adleradaptivity_module->id]);
        $this->assertNotEmpty($remainingInstance);
    }

    public function test_delete_instance_failure() {
        global $DB;

        // Try to delete a non-existing instance.
        $nonExistingId = 999999; // This ID should be non-existing.

        $this->expectException(moodle_exception::class);

        try {
            adleradaptivity_delete_instance($nonExistingId);
        } finally {
            // Ensure that no instances were deleted.
            $this->assertCount(0, $DB->get_records('adleradaptivity'));
        }
    }


    public function test_delete_instance_failure_due_to_question_deletion_failure() {
        global $DB;
        $generator = $this->getDataGenerator();

        // Create a complex module instance with test questions.
        $complex_adleradaptivity_module = $this->plugin_generator->create_course_with_test_questions($generator);

        // Sign in as user.
        $this->setUser($complex_adleradaptivity_module['user']);

        // Generate answer data.
        $answerdata = $this->plugin_generator->generate_answer_question_parameters('correct', false, $complex_adleradaptivity_module);

        // Create an attempt.
        $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // Mock the repository object and make it throw an exception.
        $mockRepo = $this
            ->getMockBuilder(adleradaptivity_question_repository::class)
            ->onlyMethods(['delete_question_by_id'])
            ->setConstructorArgs([di::get(moodle_database::class)])
            ->getMock();
        $mockRepo->method('delete_question_by_id')->willThrowException(new moodle_exception('Could not delete'));
        di::set(adleradaptivity_question_repository::class, $mockRepo);

        // verify created elements before deletion
        $this->assertCount(1, $DB->get_records('adleradaptivity'));
        $this->assertCount(2, $DB->get_records('adleradaptivity_tasks'));
        $this->assertCount(1, $DB->get_records('adleradaptivity_questions'));
        $this->assertCount(1, $DB->get_records('adleradaptivity_attempts'));

        $this->expectException(moodle_exception::class);

        try {
            // Try to delete the complex instance.
            adleradaptivity_delete_instance($complex_adleradaptivity_module['module']->id);
        } finally {
            // From my understanding these checks are not possible for postgresql databases as they don't allow rolling back sub-transactions
            // Overall the behaviour should still be correct, but as this code is executed as part of a higher level transaction
            // the transaction is not yet rolled back and therefore the modifications are not yet undone
            if ($DB->get_dbfamily() !== 'postgres') {
                // Ensure that no instances were deleted.
                $this->assertCount(1, $DB->get_records('adleradaptivity'), 'The module should not be deleted');
                $this->assertCount(2, $DB->get_records('adleradaptivity_tasks'), 'The task should not be deleted');
                $this->assertCount(1, $DB->get_records('adleradaptivity_questions'), 'The question should not be deleted');
                $this->assertCount(1, $DB->get_records('adleradaptivity_attempts'), 'The attempt should not be deleted');
            }
        }
    }

    public function test_adleradaptivity_get_coursemodule_info() {
        // Create a course
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Create an instance of the adleradaptivity module
        $adleradaptivity = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id, 'name' => 'Test Adler Adaptivity', 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'showdescription' => 1]);

        // Get the course module object
        $cm = get_coursemodule_from_instance('adleradaptivity', $adleradaptivity->id, $course->id);

        // Call the function
        $result = adleradaptivity_get_coursemodule_info($cm);

        // Verify the result
        $this->assertInstanceOf(cached_cm_info::class, $result);
        $this->assertEquals('Test Adler Adaptivity', $result->name);

        // Verify the description field
        if ($cm->showdescription) {
            $this->assertNotEmpty($result->content);
        }

        // Verify the custom completion rule
        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $this->assertArrayHasKey('default_rule', $result->customdata['customcompletionrules']);
            $this->assertEquals('some random string to make the completion rule work', $result->customdata['customcompletionrules']['default_rule']);
        }
    }

    public function test_adleradaptivity_get_coursemodule_info_instance_not_found() {
        $adleradaptivity_repository_mock = Mockery::mock(adleradaptivity_repository::class);
        $adleradaptivity_repository_mock->shouldReceive('get_instance_by_instance_id')->andReturn(false);
        di::set(adleradaptivity_repository::class, $adleradaptivity_repository_mock);

        $cm = new stdClass();
        $cm->instance = -1;

        // Call the function
        $result = adleradaptivity_get_coursemodule_info($cm);

        // Verify the result
        $this->assertFalse($result);
    }
}