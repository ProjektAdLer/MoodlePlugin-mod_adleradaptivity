<?php

declare(strict_types=1);

namespace mod_adleradaptivity\completion;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use core_completion\activity_custom_completion;
use dml_exception;
use mod_adleradaptivity\local\helpers;
use moodle_exception;
use question_attempt;
use question_usage_by_activity;
use stdClass;

/**
 * Activity custom completion subclass for the adleradaptivity activity.
 *
 * Class for defining mod_adleradaptivity's custom completion rule and fetching the completion statuse
 *
 * @package   mod_adleradaptivity
 * @copyright 2023 Markus Heck
 */
class custom_completion extends activity_custom_completion {
    /** Check if task is completed. If task is optional, it is considered completed.
     * TODO: move to helpers
     *
     * @param question_usage_by_activity $quba The question usage object.
     * @param stdClass $task The task object.
     * @return bool
     * @throws moodle_exception
     */
    private function check_task_completed(question_usage_by_activity $quba, stdClass $task): bool {
        $task_success = false;

        // handle task optional
        if ($task->optional) {
            $task_success = true;
        }

        foreach(helpers::load_questions_by_task_id($task->id) as $question) {
            // get slot of question
            foreach($quba->get_slots() as $slot) {
                if ($quba->get_question($slot)->id == $question->questionid) {
                    $slot_of_question = $slot;
                    break;
                }
            }
            if (!$slot_of_question) {
                throw new moodle_exception('question_not_found', 'question', '', null, 'Question for slot not found');
            }

            // check whether question was answered correctly
            $question_attempt = $quba->get_question_attempt($slot_of_question);
            $is_correct = $this->check_question_correctly_answered($question_attempt);
            // TODO: this only works if the results are already stored in the db. It ignores the results from the current request.

            // if question was answered correctly and question difficulty is equal or above required_difficulty, set task_success to true
            if ($is_correct && $question->difficulty >= $task->required_difficulty) {
                $task_success = true;
            }
        }

        return $task_success;
    }

    /**
     * Check if question was answered correctly.
     * load correct question state. The one in the database is garbage.
     * It has to be recalculated every time it is required. The following code was originally taken from
     * question/type/rendererbase.php -> combined_feedback()
     *
     * //TODO: move to helpers
     *
     * @param question_attempt $question_attempt The question attempt object.
     * @return bool True if the question was answered correctly, false otherwise.
     * @throws moodle_exception
     */
    private function check_question_correctly_answered(question_attempt $question_attempt): bool {
        $question = $question_attempt->get_question();

        $response = $question_attempt->get_last_qt_data();
        // This method calculates the state of the question.
        // It takes the $fractions from the questions and returns the following states
        // fraction <= 0: question_state::$gradedwrong
        // fraction = 1: question_state::$gradedright
        // else (<1 && > 0): question_state::$gradedpartial
        // This should also work just fine for manual grading because this also sets the fraction value like automatic grading.
        list($fraction, $state) = $question->grade_response($response);

        // this returns true if $state is of types question_state::$gradedright or question_state::$mangrright
        // and therefore is also correct for manual grading, although manual grading is irrelevant here because the
        // state object used here is calculated above and therefore always of type "automatic grading"
        return $state->is_correct();
    }

    /**
     * Check element successfully completed.
     * TODO: move to helpers
     *
     * @return bool True if the element is completed successfully, false otherwise.
     * @throws dml_exception If the database query fails.
     * @throws moodle_exception If the question usage cannot be loaded.
     */
    protected function check_module_completed(): bool {
        $quba = helpers::load_or_create_question_usage($this->cm->id);
        $tasks = helpers::load_tasks_by_instance_id($this->cm->instance);

        // check if all tasks are completed
        foreach ($tasks as $task) {
            if (!$this->check_task_completed($quba, $task)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        switch ($rule) {
            case 'default_rule':
                $status = static::check_module_completed();
                break;
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'default_rule',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $minattempts = $this->cm->customdata['customcompletionrules']['default_rule'] ?? 0;
        $description['default_rule'] = "blub default_rule blub";

        return $description;
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
//            'completionview',
            'default_rule',
        ];
    }
}
