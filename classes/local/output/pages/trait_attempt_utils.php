<?php

namespace mod_adleradaptivity\local\output\pages;


use context_module;
use moodle_exception;
use stdClass;

trait trait_attempt_utils {
    /**
     * Checks if the user has the necessary permissions to view or edit the specified attempt.
     *
     * @param context_module $module_context Permission checks will be executed on that context
     * @param null|stdClass $adleradaptivity_attempt The adler attempt object (DB object), null if no attempt is specified.
     * @throws required_capability_exception
     * @throws dml_exception
     */
    private function check_attempt_permissions(context_module $module_context, null|stdClass $adleradaptivity_attempt): void {
        if ($this->is_user_accessing_his_own_attempt($adleradaptivity_attempt)) {
            $this->logger->trace('No attempt id specified or specified attempt is the users own attempt -> user will use his own attempt, adler attempt id:' . $adleradaptivity_attempt->id);
            require_capability('mod/adleradaptivity:create_and_edit_own_attempt', $module_context);
        } else {
            $this->logger->info('User tries to open an attempt that is not his own, adler attempt id:' . $adleradaptivity_attempt->id);
            require_capability('mod/adleradaptivity:view_and_edit_all_attempts', $module_context);
        }
    }

    /**
     * @param stdClass|null $adleradaptivity_attempt db object
     * @return bool True if accessing their own attempt, false otherwise.
     */
    private function is_user_accessing_his_own_attempt(?stdClass $adleradaptivity_attempt): bool {
        return $adleradaptivity_attempt === null || $adleradaptivity_attempt->user_id == $this->user->id;
    }
}