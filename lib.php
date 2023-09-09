<?php

use core_completion\api as completion_api;

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function adleradaptivity_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}

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
function adleradaptivity_update_instance($moduleinstance, $mform = null): bool {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('adleradaptivity2', $moduleinstance);
}

/** The [modname]_delete_instance() function is called when the activity
 * deletion is confirmed. It is responsible for removing all data associated
 * with the instance.
 *
 * @param $id
 * @return bool
 */
function adleradaptivity_delete_instance($id): bool {
//    global $DB;
//
//    $exists = $DB->get_record('adleradaptivity2', array('id' => $id));
//    if (!$exists) {
//        return false;
//    }
//
//    $DB->delete_records('adleradaptivity2', array('id' => $id));
//
//    return true;
}

function adleradaptivity_extend_settings_navigation(settings_navigation $settings, navigation_node $adleradaptivity_node) {
    global $CFG;

    require_once($CFG->libdir . '/questionlib.php');

    if (has_capability('mod/adleradaptivity:edit', $settings->get_page()->context)) {
        $url = new moodle_url('/mod/adleradaptivity/edit_questions.php', ['id' => $settings->get_page()->cm->id]);
        $adleradaptivity_node->add(get_string('menu_edit_questions', 'adleradaptivity'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('t/edit', ''));
    }

    question_extend_settings_navigation($adleradaptivity_node, $settings->get_page()->cm->context);
}