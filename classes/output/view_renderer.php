<?php

namespace mod_adleradaptivity\output;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use completion_info;
use html_writer;
use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\local\completion_helpers;
use moodle_exception;
use moodle_url;
use plugin_renderer_base;
use qbank_previewquestion\question_preview_options;
use question_usage_by_activity;
use stdClass;

class view_renderer extends plugin_renderer_base {
    /**
     * Renders the form with the tasks with its questions.
     *
     * @param array $tasks
     * @param question_usage_by_activity $quba
     * @return string
     */
    public function render_module_view_page(array $tasks, question_usage_by_activity $quba, stdClass $cm, stdClass $course): string {
        $cmid = $cm->id;

        $slots = $quba->get_slots();
        // Define the URL for form submission
        $actionurl = new moodle_url('/mod/adleradaptivity/processattempt.php', ['id' => $cmid, 'attempt' => $quba->get_id()]);

        // start generating output
        $output = '';


        // Start the question form with the action URL
        $output .= html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl->out(false), 'enctype' => 'multipart/form-data', 'id' => 'responseform']);

        // Hidden fields for sesskey and possibly other data need to pass through the form to the processattempt page
        $output .= html_writer::start_tag('div');
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $output .= html_writer::end_tag('div');

        $output .= $this->render_content($tasks, $quba, $cm, $course);

        $output .= html_writer::end_tag('form');


        return $output;
    }

    /**
     * Renders the tasks with its questions of the adleradaptivity module.
     *
     * @param array $tasks Array of task objects with associated questions.
     * @return string HTML to output.
     * @throws coding_exception If getting the translation string failed
     * @throws moodle_exception If the question cannot be rendered.
     */
    private function render_content($tasks, question_usage_by_activity $quba, stdClass $cm, stdClass $course): string {
        $completion = new completion_info($course);

        $data = [
            'module_completed' => answer_questions::determine_module_completion_status($completion, $cm) == completion_helpers::STATUS_CORRECT,
            'tasks' => []
        ];

        foreach ($tasks as $task_id => $task) {
            $task_status = completion_helpers::check_task_status($quba, $task_id, $task['required_difficulty']);

            $taskData = [
                'title' => $task['title'],
                'optional' => $task['required_difficulty'] === null,
                'difficulty' => $task['required_difficulty'] !== null ? $this->get_difficulty_label($task['required_difficulty']) : '',
                'status_success' => in_array($task_status, [completion_helpers::STATUS_CORRECT, completion_helpers::STATUS_OPTIONAL_INCORRECT, completion_helpers::STATUS_OPTIONAL_NOT_ATTEMPTED]),
                'status_message' => get_string($this->get_task_status_message_translation_key($task_status), 'mod_adleradaptivity'),
                'status_class' => $this->get_task_status_class($task_status),
                'questions' => []
            ];

            foreach ($task['questions'] as $question) {
                $options = new question_preview_options($question['question']);
                $options->load_user_defaults();
                $options->set_from_request();

                $taskData['questions'][] = [
                    'content' => $quba->render_question($question['slot'], $options, $this->get_difficulty_label($question['difficulty'])),
                    'status_best_try' => completion_helpers::check_question_answered_correctly_once($quba->get_question_attempt($question['slot'])),
                ];
            }

            $data['tasks'][] = $taskData;
        }

        return $this->render_from_template('mod_adleradaptivity/questions', $data);
    }

    /**
     * @param string $status One of the STATUS_* constants from completion_helpers.
     * @return string The translation key for the status message.
     */
    private function get_task_status_message_translation_key(string $status): string {
        return match ($status) {
            completion_helpers::STATUS_NOT_ATTEMPTED => 'view_task_status_not_attempted',
            completion_helpers::STATUS_CORRECT => 'view_task_status_correct',
            completion_helpers::STATUS_INCORRECT => 'view_task_status_incorrect',
            completion_helpers::STATUS_OPTIONAL_NOT_ATTEMPTED => 'view_task_status_optional_not_attempted',
            completion_helpers::STATUS_OPTIONAL_INCORRECT => 'view_task_status_optional_incorrect',
            default => 'view_task_status_unknown',
        };
    }

    private function get_task_status_class(string $status): string {
        return match ($status) {
            completion_helpers::STATUS_NOT_ATTEMPTED => 'task-not-attempted',
            completion_helpers::STATUS_CORRECT => 'task-correct',
            completion_helpers::STATUS_INCORRECT => 'task-incorrect',
            completion_helpers::STATUS_OPTIONAL_NOT_ATTEMPTED => 'task-optional-not-attempted',
            completion_helpers::STATUS_OPTIONAL_INCORRECT => 'task-optional-incorrect',
            default => 'unknown',
        };
    }

    /**
     * Converts a difficulty code to a human-readable label.
     *
     * @param int|null $difficulty The difficulty code.
     * @return string The difficulty label.
     * @throws coding_exception
     */
    private function get_difficulty_label(int|null $difficulty): string {
        $difficulties = [
            0 => get_string('difficulty_0', 'mod_adleradaptivity'),
            100 => get_string('difficulty_100', 'mod_adleradaptivity'),
            200 => get_string('difficulty_200', 'mod_adleradaptivity')
        ];

        return $difficulties[$difficulty] ?? 'unknown';
    }
}
