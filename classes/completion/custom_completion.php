<?php

declare(strict_types=1);

namespace mod_adleradaptivity\completion;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use core_completion\activity_custom_completion;
use dml_exception;
use mod_adleradaptivity\local\completion_helpers;
use mod_adleradaptivity\local\helpers;
use moodle_exception;

/**
 * Activity custom completion subclass for the adleradaptivity activity.
 *
 * Class for defining mod_adleradaptivity's custom completion rule and fetching the completion statuse
 *
 * @package   mod_adleradaptivity
 * @copyright 2023 Markus Heck
 */
class custom_completion extends activity_custom_completion {
    /**
     * Check element successfully completed.
     * This method will not create a new attempt if there is none. In this case the module is considered as not completed.
     *
     * @return bool True if the element is completed successfully, false otherwise.
     * @throws dml_exception If the database query fails.
     * @throws moodle_exception If the question usage cannot be loaded.
     */
    protected function check_module_completed(): bool {
        $quba = helpers::load_or_create_question_usage(intval($this->cm->id), null, false);
        $tasks = helpers::load_tasks_by_instance_id($this->cm->instance);

        // check if there is an attempt, if not the module was not yet started and therefore not completed
        if ($quba === false) {
            return false;
        }

        // check if all tasks are completed
        foreach ($tasks as $task) {
            $task_status = completion_helpers::check_task_status($quba, $task);
            if (in_array($task_status, ['notAttempted', 'incorrect'])) {
                // the other states correct, optional_notAttempted and optional_incorrect are considered as completed in this context
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
     * @throws moodle_exception
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        $status = match ($rule) {
            'default_rule' => static::check_module_completed(),
            default => throw new moodle_exception('invalid_parameter_exception', 'adleradaptivity', '', null, 'Invalid rule: ' . $rule),
        };

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
        return [
            'default_rule' => get_string('rule_description_default_rule', 'adleradaptivity'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'default_rule',
        ];
    }
}
