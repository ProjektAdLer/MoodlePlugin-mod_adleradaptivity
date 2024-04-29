<?php

namespace mod_adleradaptivity\local\output\pages;

global $CFG;  // TODO remove and maybe require_once('../../config.php'); also

use bootstrap_renderer;
use coding_exception;
use context_course;
use context_module;
use core\context;
use core\context\module;
use dml_exception;
use html_table;
use html_table_row;
use html_writer;
use invalid_parameter_exception;
use local_logging\logger;
use mod_adleradaptivity\event\course_module_instance_list_viewed;
use mod_adleradaptivity\local\db\adleradaptivity_repository;
use mod_adleradaptivity\local\db\moodle_core_repository;
use moodle_exception;
use moodle_page;
use moodle_url;
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
 * Handles the rendering of the adleradaptivity module's index page.
 * It's responsibilities are the same as index.php in other plugins.
 */
class index_page {
    private moodle_page $page;
    private bootstrap_renderer $output;

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
        list($course) = $this->process_request_parameters();
        $course_context = context_course::instance($course->id);

        require_login($course);

        // Trigger course_module_instance_list_viewed event
        $params = [
            'context' => $course_context
        ];
        $event = course_module_instance_list_viewed::create($params);
        $event->trigger();

        // generating the output
        $this->define_page_meta_information($course);
        index_page::render_page($course);
    }

    private function setup_instance_variables(): void {
        global $PAGE, $OUTPUT;
        $this->page = $PAGE;
        $this->output = $OUTPUT;
    }

    /**
     * @param stdClass $course The course (DB) object
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_page(stdClass $course): void {
        $renderer = $this->page->get_renderer('mod_adleradaptivity', 'index');

        echo $this->output->header();
        echo $renderer->render_page($course);
        echo $this->output->footer();
    }

    /**
     * @return array An array containing the processed attempt ID, course module, course, and module instance objects.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function process_request_parameters(): array {
        $course_id = required_param('id', PARAM_INT);

        $course = get_course($course_id);
        return array($course);
    }

    /**
     * @param stdClass $course
     * @return void
     * @throws coding_exception
     */
    private function define_page_meta_information(stdClass $course): void {
        $this->page->set_url('/mod/adleradaptivity/index.php', ['id' => $course->id]);
        $this->page->set_pagelayout('incourse');
        $this->page->navbar->add(get_string('modulenameplural', 'adleradaptivity'));
        $this->page->set_title(get_string('modulenameplural', 'adleradaptivity'));
        $this->page->set_heading($course->fullname);
    }
}
