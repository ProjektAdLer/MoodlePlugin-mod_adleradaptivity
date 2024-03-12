<?php

namespace mod_adleradaptivity\local\output\pages;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/adleradaptivity/locallib.php');

use bootstrap_renderer;
use coding_exception;
use context_module;
use core\context;
use dml_exception;
use local_logging\logger;
use moodle_database;
use moodle_exception;
use moodle_page;
use question_engine;
use question_usage_by_activity;
use require_login_exception;
use required_capability_exception;
use stdClass;

use mod_adleradaptivity\local\db\adleradaptivity_attempt_repository;
use mod_adleradaptivity\local\db\question_repository;
use mod_adleradaptivity\local\db\task_repository;
use mod_adleradaptivity\local\helpers;

// TODO: phpdocs

class view_page {
    private moodle_database $db;  // TODO: no db here
    private moodle_page $page;
    private bootstrap_renderer $output;
    private stdClass $user;
    private task_repository $task_repository;
    private question_repository $question_repository;
    private adleradaptivity_attempt_repository $adleradaptivity_attempt_repository;
    private logger $logger;  // TODO: use logger

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
        $this->check_attempt_permissions($course, $cm, $module_context, $adleradaptivity_attempt);

        // continue setting up variables
        $tasks = $this->load_tasks_with_questions_sorted_by_difficulty($quba, $module_instance);


        // Trigger course_module_viewed event and completion.
        adleradaptivity_view($module_instance, $course, $cm, $module_context);  // TODO: is this actually required (as it looks like is is a requierd method in locallib.php)?


        // generating the output
        $this->define_page_meta_information($cm, $module_instance, $course, $module_context);
        view_page::render_page($tasks, $quba, $cm, $course);
    }

    private function setup_instance_variables(): void {
        global $DB, $PAGE, $OUTPUT, $USER;
        $this->db = $DB;
        $this->page = $PAGE;
        $this->output = $OUTPUT;
        $this->user = $USER;

        $this->task_repository = new task_repository();
        $this->question_repository = new question_repository();
        $this->adleradaptivity_attempt_repository = new adleradaptivity_attempt_repository();

        $this->logger = new logger('mod_adleradaptivity', 'view_page.php');
    }

    /**
     * Sorts the questions in the tasks by difficulty.
     *
     * @param array $tasks The tasks to sort ($tasks[].questions[].difficulty).
     * @return array The sorted tasks.
     */
    public static function sort_questions_in_tasks_by_difficulty(array $tasks): array {
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
     * Checks if the user has the necessary permissions to view the page with the given parameters.
     *
     * // TODO documentation
     * @param null|stdClass $adleradaptivity_attempt The adler attempt object (DB object), null if no attempt is specified.
     * @throws moodle_exception If the user does not have the necessary permissions.
     */
    public function check_attempt_permissions(stdClass $course, stdClass $cm, context $module_context, null|stdClass $adleradaptivity_attempt): void {
        if ($this->is_user_accessing_his_own_attempt($adleradaptivity_attempt)) {
            $this->logger->trace('No attempt id specified or specified attempt is the users own attempt -> user will use his own attempt');
            require_capability('mod/adleradaptivity:create_and_edit_own_attempt', $module_context);
        } else {
            $this->logger->info('User tries to open an attempt that is not his own, id:' . $adleradaptivity_attempt->attempt_id);
            require_capability('mod/adleradaptivity:view_and_edit_all_attempts', $module_context);
        }
    }

    public function render_page($tasks, $quba, $cm, $course): void {
        $renderer = $this->page->get_renderer('mod_adleradaptivity', 'view');

        echo $this->output->header();
        echo $renderer->render_module_view_page($tasks, $quba, $cm, $course);
        echo $this->output->footer();
    }

    /**
     * @return question_usage_by_activity
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function load_or_create_question_usage_by_attempt_id(int $attempt_id, stdClass $cm): question_usage_by_activity {
        // Load quiz attempt if attempt parameter is not -1, otherwise create new attempt
        if ($attempt_id === -1) {
            $quba = helpers::load_or_create_question_usage($cm->id);
        } else {
            // Load the attempt
            $quba = question_engine::load_questions_usage_by_activity($attempt_id);
        }
        return $quba;
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    private function process_request_parameters(): array {
        $cmid = optional_param('id', 0, PARAM_INT);

        $attempt_id = optional_param('attempt', -1, PARAM_INT);

        $cm = get_coursemodule_from_id('adleradaptivity', $cmid, 0, false, MUST_EXIST);
        $course = $this->db->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $module_instance = $this->db->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);
        return array($attempt_id, $cm, $course, $module_instance);
    }

    /**
     * @param mixed $cm
     * @param mixed $module_instance
     * @param mixed $course
     * @param bool|context\module $module_context
     * @return void
     * @throws coding_exception
     */
    private function define_page_meta_information(mixed $cm, mixed $module_instance, mixed $course, bool|context\module $module_context): void {
        $this->page->set_url('/mod/adleradaptivity/view.php', array('id' => $cm->id));
        $this->page->set_title(format_string($module_instance->name));
        $this->page->set_heading(format_string($course->fullname));
        $this->page->set_context($module_context);
    }

    private function load_tasks_with_questions(question_usage_by_activity $quba, mixed $module_instance): array {
        $slots = $quba->get_slots();

        $tasks = [];  // This will be an array of tasks, each containing its questions
        foreach ($slots as $slot) {
            $tasks = $this->insert_question_from_slot_into_tasks_array($quba, $slot, $module_instance, $tasks);
        }
        return $tasks;
    }

    /**
     * Load the tasks with their questions, questions are sorted by difficulty inside each task.
     *
     * @param question_usage_by_activity $quba
     * @param mixed $module_instance
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function load_tasks_with_questions_sorted_by_difficulty(question_usage_by_activity $quba, mixed $module_instance): array {
        $tasks = $this->load_tasks_with_questions($quba, $module_instance);
        return view_page::sort_questions_in_tasks_by_difficulty($tasks);
    }

    /**
     * @param question_usage_by_activity $quba
     * @param mixed $slot
     * @param mixed $module_instance
     * @param array $tasks
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function insert_question_from_slot_into_tasks_array(question_usage_by_activity $quba, mixed $slot, mixed $module_instance, array $tasks): array {
        $question = $quba->get_question($slot);
        $adaptivity_question = $this->question_repository->get_adleradaptivity_question_by_question_bank_entries_id($question->questionbankentryid);
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
        return $tasks;
    }

    /**
     * @param stdClass|null $adleradaptivity_attempt
     * @return bool
     */
    private function is_user_accessing_his_own_attempt(?stdClass $adleradaptivity_attempt): bool {
        return $adleradaptivity_attempt === null || $adleradaptivity_attempt->user_id == $this->user->id;
    }

    /**
     * @param mixed $course
     * @param mixed $cm
     * @param $module_context
     * @return void
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function check_basic_permissions(mixed $course, mixed $cm, $module_context): void {
        require_login($course, false, $cm);
        require_capability('mod/adleradaptivity:view', $module_context);
    }
}
