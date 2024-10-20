<?php

namespace mod_adleradaptivity\local\output\pages;

use coding_exception;
use completion_info;
use core\di;
use dml_exception;
use dml_transaction_exception;
use local_logging\logger;
use mod_adleradaptivity\local\db\moodle_core_repository;
use moodle_database;
use moodle_exception;
use moodle_url;
use question_engine;
use question_usage_by_activity;
use require_login_exception;
use stdClass;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');  // required for question_engine

/**
 * Handles the processattempt page.
 * This page does not render anything, it only processes the attempt and redirects to the view page.
 */
class processattempt_page {
    private moodle_core_repository $moodle_core_repository;
    private logger $logger;
    private int $time_now;

    /**
     * Constructs and completely renders the page
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws require_login_exception
     * @throws moodle_exception
     */
    public function __construct() {
        // setup variables
        $this->setup_instance_variables();
        list($cm, $course, $quba) = $this->process_request_parameters();

        require_login($course, false, $cm);
        require_sesskey();

        // generating the output
        $this->logger->info('Processing attempt ' . $quba->get_id() . ' for course module ' . $cm->id);
        $this->process_attempt($quba, $course, $cm);

        // redirect to the view page
        redirect(new moodle_url('/mod/adleradaptivity/view.php', ['id' => $cm->id, 'attempt' => $quba->get_id()]));
    }

    /**
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    private function process_attempt(question_usage_by_activity $quba, stdClass $course, stdClass $cm): void {
        global $DB;
        $transaction = di::get(moodle_database::class)->start_delegated_transaction();
        $quba->process_all_actions($this->time_now);
        question_engine::save_questions_usage_by_activity($quba);

        // Update completion state
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $transaction->allow_commit();
    }

    private function setup_instance_variables(): void {
        $this->logger = new logger('mod_adleradaptiviy', 'processattempt');
        $this->time_now = time();  # Saving time at request start to use the actual submission time
        $this->moodle_core_repository = di::get(moodle_core_repository::class);
    }

    /**
     * @return array An array containing the course module, course and question usage by activity
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function process_request_parameters(): array {
        $cmid = optional_param('id', 0, PARAM_INT);
        $attempt_id = view_page::get_attempt_id_param();

        $cm = get_coursemodule_from_id('adleradaptivity', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $quba = view_page::get_question_usage_by_attempt_id_with_cm_verification($attempt_id, $cm,$this->moodle_core_repository);

        return array($cm, $course, $quba);
    }
}
