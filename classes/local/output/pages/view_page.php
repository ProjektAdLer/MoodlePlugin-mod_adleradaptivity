<?php

namespace mod_adleradaptivity\local\output\pages;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/adleradaptivity/locallib.php');

use bootstrap_renderer;
use coding_exception;
use context_module;
use core\context;
use core\context\module;
use dml_exception;
use invalid_parameter_exception;
use local_logging\logger;
use mod_adleradaptivity\local\db\adleradaptivity_repository;
use moodle_exception;
use moodle_page;
use question_engine;
use question_usage_by_activity;
use require_login_exception;
use required_capability_exception;
use stdClass;

use mod_adleradaptivity\local\db\adleradaptivity_attempt_repository;
use mod_adleradaptivity\local\db\adleradaptivity_question_repository;
use mod_adleradaptivity\local\db\adleradaptivity_task_repository;
use mod_adleradaptivity\local\helpers;

/**
 * Handles the rendering of the adleradaptivity module's view page.
 * It's responsibilities are the same as view.php in other plugins.
 */
class view_page {
    private moodle_page $page;
    private bootstrap_renderer $output;
    private stdClass $user;
    private adleradaptivity_task_repository $task_repository;
    private adleradaptivity_question_repository $question_repository;
    private adleradaptivity_attempt_repository $adleradaptivity_attempt_repository;
    private adleradaptivity_repository $adleradaptivity_repository;
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
    public function __construct() {
        // setup variables
        $this->setup_instance_variables();
        list($attempt_id, $cm, $course, $module_instance) = $this->process_request_parameters();
        $module_context = context_module::instance($cm->id);

        // check user is logged in and is allowed to view the page
        // The require_login check has to be done before creating a new attempt (not exactly sure why,
        // but it will fail if done afterward) and doing this as early as possible is a good idea anyway.
        $this->check_basic_permissions($course, $cm, $module_context);

        // setup attempt variables (and create new attempt if required)
        $quba = $this->load_or_create_question_usage_by_attempt_id($attempt_id, $cm);
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

        $this->task_repository = new adleradaptivity_task_repository();
        $this->question_repository = new adleradaptivity_question_repository();
        $this->adleradaptivity_attempt_repository = new adleradaptivity_attempt_repository();
        $this->adleradaptivity_repository = new adleradaptivity_repository();

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
     * Checks if the user has the necessary permissions to view or edit the specified attempt.
     *
     * @param context $module_context Permission checks will be executed on that context
     * @param null|stdClass $adleradaptivity_attempt The adler attempt object (DB object), null if no attempt is specified.
     * @throws moodle_exception If the user does not have the necessary permissions.
     */
    private function check_attempt_permissions(context $module_context, null|stdClass $adleradaptivity_attempt): void {
        if ($this->is_user_accessing_his_own_attempt($adleradaptivity_attempt)) {
            $this->logger->trace('No attempt id specified or specified attempt is the users own attempt -> user will use his own attempt, adler attempt id:' . $adleradaptivity_attempt->id);
            require_capability('mod/adleradaptivity:create_and_edit_own_attempt', $module_context);
        } else {
            $this->logger->info('User tries to open an attempt that is not his own, adler attempt id:' . $adleradaptivity_attempt->id);
            require_capability('mod/adleradaptivity:view_and_edit_all_attempts', $module_context);
        }
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
     * @param int $attempt_id The attempt id as specified in the request, -1 if no attempt id was provided
     * @param stdClass $cm course module (in moodle form)
     * @return question_usage_by_activity
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function load_or_create_question_usage_by_attempt_id(int $attempt_id, stdClass $cm): question_usage_by_activity {
        // Load quiz attempt if attempt parameter is not -1, otherwise create new attempt
        if ($attempt_id === -1) {
            $this->logger->trace('No attempt specified, loading existing attempt if exists or creating new one');
            $quba = helpers::load_or_create_question_usage($cm->id);
        } else {
            // Load the attempt
            $this->logger->trace('Loading existing attempt. Attempt ID: ' . $attempt_id);
            if ($cm->id == helpers::get_cmid_for_question_usage($attempt_id)) {
                // can only happen if attempt id was specified, otherwise only a valid one will be loaded
                $quba = question_engine::load_questions_usage_by_activity($attempt_id);
            } else {
                throw new invalid_parameter_exception('Specified attempt is not for this cm. Attempt ID: ' . $attempt_id);
            }
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

        $attempt_id = optional_param('attempt', -1, PARAM_INT);
        if ($attempt_id == 0) {
            throw new moodle_exception('invalidattemptid', 'adleradaptivity');
        }

        $cm = get_coursemodule_from_id('adleradaptivity', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $module_instance = $this->adleradaptivity_repository->get_instance_by_instance_id($cm->instance);
        return array($attempt_id, $cm, $course, $module_instance);
    }

    /**
     * @param stdClass $cm
     * @param stdClass $module_instance adleradaptivity db object
     * @param stdClass $course
     * @param module $module_context
     * @return void
     * @throws coding_exception
     */
    private function define_page_meta_information(stdClass $cm, stdClass $module_instance, stdClass $course, module $module_context): void {
        $this->page->set_url('/mod/adleradaptivity/view.php', array('id' => $cm->id));
        $this->page->set_title(format_string($module_instance->name));
        $this->page->set_heading(format_string($course->fullname));
        $this->page->set_context($module_context);
    }

    /**
     * @param question_usage_by_activity $quba
     * @param stdClass $module_instance adleradaptivity db object
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
     * @param stdClass|null $adleradaptivity_attempt db object
     * @return bool True if accessing their own attempt, false otherwise.
     */
    private function is_user_accessing_his_own_attempt(?stdClass $adleradaptivity_attempt): bool {
        return $adleradaptivity_attempt === null || $adleradaptivity_attempt->user_id == $this->user->id;
    }

    /**
     * @param stdClass $course db object
     * @param stdClass $cm moodle cm object
     * @param module $module_context
     * @throws coding_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     */
    private function check_basic_permissions(stdClass $course, stdClass $cm, module $module_context): void {
        require_login($course, false, $cm);
        require_capability('mod/adleradaptivity:view', $module_context);
    }
}
