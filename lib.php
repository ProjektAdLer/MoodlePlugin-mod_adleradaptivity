<?php

use core_completion\api as completion_api;

/** The [modname]_add_instance() function is called when the activity
 * creation form is submitted. This function is only called when adding
 * an activity and should contain any logic required to add the activity.
 *
 * @param $instancedata
 * @param $mform
 * @return int
 */
function adleradaptivity_add_instance($instancedata, $mform = null): int {
    global $DB;

    $instancedata->timemodified = time();

    $id = $DB->insert_record("adleradaptivity", $instancedata);

    // Update completion date event. This is a default feature activated for all modules (create module -> Activity completion).
    $completiontimeexpected = !empty($instancedata->completionexpected) ? $instancedata->completionexpected : null;
    completion_api::update_completion_date_event($instancedata->coursemodule, 'adleradaptivity', $id, $completiontimeexpected);

    return $id;

}

/** The [modname]_update_instance() function is called when the activity
 * editing form is submitted.
 *
 * @param $instancedata
 * @param $mform
 * @return bool
 */
function adleradaptivity_update_instance($instancedata, $mform): bool {

}

/** The [modname]_delete_instance() function is called when the activity
 * deletion is confirmed. It is responsible for removing all data associated
 * with the instance.
 *
 * @param $id
 * @return bool
 */
function adleradaptivity_delete_instance($id): bool {

}
