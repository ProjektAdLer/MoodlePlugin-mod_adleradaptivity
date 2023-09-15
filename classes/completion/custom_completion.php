<?php

declare(strict_types=1);

namespace mod_adleradaptivity\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the quiz activity.
 *
 * Class for defining mod_quiz's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given quiz instance and a user.
 *
 * @package   mod_adleradaptivity
 * @copyright 2023 Markus Heck
 */
class custom_completion extends activity_custom_completion {
    /**
     * Check element successfully completed.
     *
     * @return bool True if the element is completed successfully, false otherwise.
     */
    protected function check_element_completed(): bool {
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
                $status = static::check_element_completed();
                break;
        }
return COMPLETION_INCOMPLETE;
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
            'default_rule',
        ];
    }
}
