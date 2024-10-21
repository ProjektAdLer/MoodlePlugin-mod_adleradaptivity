<?php

namespace mod_adleradaptivity\local\output\pages;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use bootstrap_renderer;
use coding_exception;
use context_module;
use core\di;
use dml_exception;
use dml_missing_record_exception;
use invalid_parameter_exception;
use local_logging\logger;
use mod_adleradaptivity\local\db\adleradaptivity_repository;
use mod_adleradaptivity\local\db\moodle_core_repository;
use mod_adleradaptivity\moodle_core;
use moodle_exception;
use moodle_page;
use question_engine;
use question_usage_by_activity;
use require_login_exception;
use required_capability_exception;
use stdClass;

use mod_adleradaptivity\local\db\adleradaptivity_attempt_repository;
use mod_adleradaptivity\local\db\adleradaptivity_question_repository;
use mod_adleradaptivity\local\helpers;

/**
 * Handles the rendering of the adleradaptivity module's view page.
 * It's responsibilities are the same as view.php in other plugins.
 */
class view_page {
    use trait_attempt_utils;

    private moodle_page $page;
    private bootstrap_renderer $output;
    private stdClass $user;
    private logger $logger;

    /**
     * Constructs and completely renders the page
     *
     * @throws coding_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function __construct(
        private readonly adleradaptivity_question_repository $adleradaptivity_question_repository,
        private readonly adleradaptivity_attempt_repository $adleradaptivity_attempt_repository,
        private readonly adleradaptivity_repository $adleradaptivity_repository,
        private readonly moodle_core_repository $moodle_core_repository,
    ) {
        // setup variables
        $this->setup_instance_variables();
        list($attempt_id, $cm, $course, $module_instance) = $this->process_request_parameters();
        $module_context = context_module::instance($cm->id);

        // check user is logged in and is allowed to view the page
        // The require_login check has to be done before creating a new attempt (not exactly sure why,
        // but it will fail if done afterward) and doing this as early as possible is a good idea anyway.
        $this->check_basic_permissions($course, $cm, $module_context);

        // setup attempt variables (and create new attempt if required)
        try {
            $quba = $this->load_or_create_question_usage_by_attempt_id($attempt_id, $cm);
        } catch (invalid_parameter_exception $e) {
            throw new moodle_exception('invalidattemptid', 'adleradaptivity');
        }
        $adleradaptivity_attempt = $this->adleradaptivity_attempt_repository->get_adleradaptivity_attempt_by_quba_id($quba->get_id());

        // now having enough information to check permissions to access/edit the attempt
        $this->check_attempt_permissions($module_context, $adleradaptivity_attempt);

        // continue setting up variables
        $tasks = $this->load_tasks_with_questions_sorted_by_difficulty($quba, $module_instance, $module_context);


        // Trigger course_module_viewed event and completion.
        adleradaptivity_view($module_instance, $course, $cm, $module_context);


        // generating the output
        $this->define_page_meta_information($cm, $module_instance, $course, $module_context);
        view_page::render_page($tasks, $quba, $cm, $course);
    }

    private function setup_instance_variables(): void {
        global $PAGE, $OUTPUT, $USER;
        $this->page = $PAGE;
        $this->output = $OUTPUT;
        $this->user = $USER;

        $this->logger = new logger('mod_adleradaptivity', 'view_page.php');
    }

    /**
     * Sorts questions within each task by difficulty.
     *
     * @param array $tasks The tasks to sort ($tasks[].questions[].difficulty).
     * @return array The sorted tasks.
     */
    private static function sort_questions_in_tasks_by_difficulty(array $tasks): array {
        $sorted_tasks = [];
        foreach ($tasks as $key => $task) {
            usort($task['questions'], function ($a, $b) {
                return $a['difficulty'] - $b['difficulty'];
            });
            $sorted_tasks[$key] = $task;
        }

        return $sorted_tasks;
    }

    /**
     * @param array $tasks The tasks and questions to display on the page.
     * @param question_usage_by_activity $quba
     * @param stdClass $cm course module (in moodle coursemodule form)
     * @param stdClass $course The course (DB) object
     */
    private function render_page(array $tasks, question_usage_by_activity $quba, stdClass $cm, stdClass $course): void {
        $renderer = $this->page->get_renderer('mod_adleradaptivity', 'view');

        echo $this->output->header();
        echo $renderer->render_module_view_page($tasks, $quba, $cm, $course);
        echo $this->output->footer();
    }

    /**
     * @param int|null $attempt_id The attempt id as specified in the request, null if no attempt id was provided
     * @param stdClass $cm course module (in moodle form)
     * @return question_usage_by_activity
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    private function load_or_create_question_usage_by_attempt_id(int|null $attempt_id, stdClass $cm): question_usage_by_activity {
        // Load quiz attempt if attempt parameter is not null, otherwise create new attempt
        if ($attempt_id === null) {
            $this->logger->trace('No attempt specified, loading existing attempt if exists or creating new one');
            $quba = helpers::load_or_create_question_usage($cm->id);
        } else {
            // Load the attempt
            $this->logger->trace('Loading existing attempt. Attempt ID: ' . $attempt_id);
            $quba = self::get_question_usage_by_attempt_id_with_cm_verification($attempt_id, $cm, $this->moodle_core_repository);
        }
        return $quba;
    }

    /**
     * @return array An array containing the processed attempt ID, course module, course, and module instance objects.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function process_request_parameters(): array {
        $cmid = optional_param('id', 0, PARAM_INT);
        if ($cmid == 0) {
            throw new moodle_exception('invalidcoursemodule', 'adleradaptivity');
        }

        $attempt_id = self::get_attempt_id_param();

        $cm = di::get(moodle_core::class)::get_coursemodule_from_id('adleradaptivity', $cmid, 0, false, MUST_EXIST);
        $course = di::get(moodle_core::class)::get_course($cm->course);
        $module_instance = $this->adleradaptivity_repository->get_instance_by_instance_id($cm->instance);
        return array($attempt_id, $cm, $course, $module_instance);
    }

    /**
     * @param stdClass $cm
     * @param stdClass $module_instance adleradaptivity db object
     * @param stdClass $course
     * @param context_module $module_context
     * @return void
     * @throws coding_exception
     */
    private function define_page_meta_information(stdClass $cm, stdClass $module_instance, stdClass $course, context_module $module_context): void {
        $this->page->set_url('/mod/adleradaptivity/view.php', array('id' => $cm->id));
        $this->page->set_title(format_string($module_instance->name));
        $this->page->set_heading(format_string($course->fullname));
        $this->page->set_context($module_context);
    }

    /**
     * @param question_usage_by_activity $quba
     * @param stdClass $module_instance adleradaptivity db object
     * @param context_module $module_context
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function load_tasks_with_questions(question_usage_by_activity $quba, stdClass $module_instance, context_module $module_context): array {
        $slots = $quba->get_slots();

        $tasks = [];  // This will be an array of tasks, each containing its questions
        foreach ($slots as $slot) {
            $this->insert_question_from_slot_into_tasks_array($quba, $slot, $module_instance, $tasks, $module_context);
        }
        return $tasks;
    }

    /**
     * Load the tasks with their questions, questions are sorted by difficulty inside each task.
     *
     * @param question_usage_by_activity $quba
     * @param stdClass $module_instance adleradaptivity db object
     * @param context_module $module_context
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function load_tasks_with_questions_sorted_by_difficulty(question_usage_by_activity $quba, stdClass $module_instance, context_module $module_context): array {
        $tasks = $this->load_tasks_with_questions($quba, $module_instance, $module_context);
        return view_page::sort_questions_in_tasks_by_difficulty($tasks);
    }

    /**
     * @param question_usage_by_activity $quba
     * @param int $slot question bank "id"
     * @param stdClass $module_instance adleradaptivity db object
     * @param array $tasks The reference to the tasks array the question will be inserted into.
     * @param context_module $module_context
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function insert_question_from_slot_into_tasks_array(question_usage_by_activity $quba, int $slot, stdClass $module_instance, array &$tasks, context_module $module_context): void {
        $question = $quba->get_question($slot);
        $adaptivity_question = $this->question_repository->get_adleradaptivity_question_by_question_bank_entries_id($question->questionbankentryid, $module_context);
        $task = $this->task_repository->get_task_by_question_uuid($question->idnumber, $module_instance->id);

        if (!isset($tasks[$task->id])) {
            $tasks[$task->id] = [
                'title' => $task->title,
                'required_difficulty' => $task->required_difficulty,
                'questions' => []
            ];
        }

        $tasks[$task->id]['questions'][] = [
            'question' => $question,
            'slot' => $slot,
            'difficulty' => $adaptivity_question->difficulty
        ];
    }

    /**
     * @param stdClass $course db object
     * @param stdClass $cm moodle cm object
     * @param context_module $module_context
     * @throws coding_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     */
    private function check_basic_permissions(stdClass $course, stdClass $cm, context_module $module_context): void {
        require_login($course, false, $cm);
        require_capability('mod/adleradaptivity:view', $module_context);
    }

    /**
     * @return int|null
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_attempt_id_param(): int|null {
        $attempt_id = optional_param('attempt', null, PARAM_RAW);
        if ($attempt_id !== null &&
            !is_int($attempt_id) &&
            !(is_string($attempt_id) && ctype_digit($attempt_id))) {
            throw new moodle_exception('invalidattemptid', 'adleradaptivity');
        }
        if ($attempt_id !== null) {
            $attempt_id = intval($attempt_id);
            if ($attempt_id < 0) {
                throw new moodle_exception('invalidattemptid', 'adleradaptivity');
            }
        }
        return $attempt_id;
    }

    /**
     * @param int $attempt_id
     * @param stdClass $cm
     * @return question_usage_by_activity
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function get_question_usage_by_attempt_id_with_cm_verification(int $attempt_id, stdClass $cm, moodle_core_repository $moodle_core_repository): question_usage_by_activity {
        try {
            $cmid_of_question_usage = $moodle_core_repository->get_cmid_by_question_usage_id($attempt_id);
        } catch (dml_missing_record_exception $e) {
            throw new invalid_parameter_exception('Specified attempt does not exist. Attempt ID: ' . $attempt_id);
        }
        if ($cm->id == $cmid_of_question_usage) {
            // can only happen if attempt id was specified, otherwise only a valid one will be loaded
            $quba = question_engine::load_questions_usage_by_activity($attempt_id);
        } else {
            throw new invalid_parameter_exception('Specified attempt is not for this cm. Attempt ID: ' . $attempt_id);
        }
        return $quba;
    }
}
