<?php

namespace mod_adleradaptivity\local\output\pages;

global $CFG;

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
    private logger $logger;

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

        $this->logger = new logger('mod_adleradaptivity', 'index_page.php');
    }

    /**
     * @param stdClass $course The course (DB) object
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_page(stdClass $course): void {
        echo $this->output->header();
        echo $this->output->heading(get_string('modulenameplural', 'adleradaptivity'), 2);

        // Get all the appropriate data.
        if (!$adleradaptivitys = get_all_instances_in_course('adleradaptivity', $course)) {
            notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'adleradaptivity')), "../../course/view.php?id=$course->id");
            die;
        }

        // Configure table for displaying the list of instances.
        $headings = [];
        $align = [];

        if (course_format_uses_sections($course->format)) {
            $headings[] = get_string('sectionname', 'format_' . $course->format);
            $align[] = 'left';
        }

        $headings[] = get_string('name');
        $align[] = 'left';

        $table = new html_table();
        $table->head = $headings;
        $table->align = $align;

        // Populate the table with the list of instances.
        foreach ($adleradaptivitys as $adleradaptivity) {
            $row = new html_table_row();

            if (course_format_uses_sections($course->format)) {
                $row->cells[] = get_section_name($course, $adleradaptivity->section);
            }

            $row->cells[] = html_writer::link(
                new moodle_url('/mod/adleradaptivity/view.php', ['id' => $adleradaptivity->coursemodule]),
                format_string($adleradaptivity->name)
            );

            $table->data[] = $row;
        }

        // Display the table.
        echo html_writer::table($table);

        echo $this->output->footer();

//        $renderer = $this->page->get_renderer('mod_adleradaptivity', 'view');
//
//        echo $this->output->header();
//        echo $renderer->render_module_view_page($tasks, $quba, $cm, $course);
//        echo $this->output->footer();
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
