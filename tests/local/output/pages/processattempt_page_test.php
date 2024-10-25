<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace mod_adleradaptivity\local\output\pages;

use context_module;
use core\di;
use Mockery;
use mod_adleradaptivity\lib\adler_testcase;
use mod_adleradaptivity\local\db\moodle_core_repository;
use mod_adleradaptivity\moodle_core;
use question_engine;
use required_capability_exception;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

class processattempt_page_test extends adler_testcase {
    public function check_attempt_permission_provider() {
        return [
            'valid attempt' => [true],
            'invalid attempt' => [false],
        ];
    }

    /**
     * Test check_attempt_permission method.
     *
     * @dataProvider check_attempt_permission_provider
     */
    public function test_check_attempt_permission($isValid) {
        global $USER;

        // Create a user and a course
        $user = $this->getDataGenerator()->create_user();
        $other_user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($other_user->id, $course->id);

        // Create adler adaptivity instance
        $adleradaptivity = $this->getDataGenerator()->create_module('adleradaptivity', ['course' => $course->id]);

        // Create an attempt
        $module_context = context_module::instance($adleradaptivity->cmid);
        $moodle_attempt = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $module_context);
        $moodle_attempt->set_preferred_behaviour(1);
        question_engine::save_questions_usage_by_activity($moodle_attempt);
        $attempt = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity')->create_mod_adleradaptivity_attempt($moodle_attempt->get_id(), $user->id);

        // Login as the user
        if ($isValid) {
            $this->setUser($user);
        } else {
            $this->setUser($other_user);
        }

        // Mock redirect call
        $moodle_core_mock = Mockery::mock(moodle_core::class);
        if ($isValid) {
            $moodle_core_mock->shouldReceive('redirect');
        } else {
            $moodle_core_mock->shouldNotReceive('redirect');
        }
        di::set(moodle_core::class, $moodle_core_mock);

        // Arrange
        $_GET['id'] = $adleradaptivity->cmid;
        $_GET['attempt'] = $moodle_attempt->get_id();
        $_GET['sesskey'] = $USER->sesskey; // Add the sesskey parameter

        if (!$isValid) {
            $this->expectException(required_capability_exception::class);
        }

        // Load the page to test
        di::get(processattempt_page::class);
    }


// TODO: remaining two plugins
// TODO: issues on github

}