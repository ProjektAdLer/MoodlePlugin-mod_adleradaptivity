<?php

use core_completion\api as completion_api;
use mod_adleradaptivity\local\helpers;

// TODO: taken from mod/readme.md
//lib.php: any/all functions defined by the module should be in here.
//constants should be defined using MODULENAME_xxxxxx
//         functions should be defined using modulename_xxxxxx
//
//         There are a number of standard functions:
//
//         modulename_add_instance()
//         modulename_update_instance()
//         modulename_delete_instance()
//
//         modulename_user_complete()
//         modulename_user_outline()
//
//         modulename_cron()
//
//         modulename_print_recent_activity()
//


/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function adleradaptivity_supports($feature) {
    switch ($feature) {
//        case FEATURE_COMPLETION_TRACKS_VIEWS:  // seems to add the "Require view" checkbox to the "when conditions are met" in the "activity completion" section of the activity settings
        case FEATURE_COMPLETION_HAS_RULES:  // custom completion rules
        case FEATURE_USES_QUESTIONS:
        case FEATURE_MOD_INTRO:
        case FEATURE_BACKUP_MOODLE2:
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

//    old_module = DB->get_record('adleradaptivity', array('id' => $moduleinstance->instance));

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('adleradaptivity', $moduleinstance);
}

/** The adleradaptivity_delete_instance() function is called when the activity
 * deletion is confirmed. It is responsible for removing all data associated
 * with the instance.
 * questions itself are not deleted here as they belong to the course, not to the module. The adleradaptivity_questions are deleted.
 *
 * @param $instance_id int The instance id of the module to delete.
 * @return bool true if success, false if failed.
 * @throws dml_transaction_exception if the transaction failed and could not be rolled back.
 */
function adleradaptivity_delete_instance(int $instance_id): bool {
    // TODO: https://github.com/ProjektAdLer/MoodlePluginModAdleradaptivity/issues/1
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    try {
        // first ensure that the module instance exists
        $DB->get_record('adleradaptivity', array('id' => $instance_id), '*', MUST_EXIST);

        // load all attempts related to $instance_id
        $cm = get_coursemodule_from_instance('adleradaptivity', $instance_id, 0, false, MUST_EXIST);
        $attempts = helpers::load_adleradaptivity_attempt_by_cmid($cm->id);
        // delete all attempts
        foreach ($attempts as $attempt) {
            $DB->delete_records('adleradaptivity_attempts', array('attempt_id' => $attempt->attempt_id));
            question_engine::delete_questions_usage_by_activity($attempt->attempt_id);
        }

        // delete the module itself and all related tasks and questions
        // load required data
        $adler_tasks = $DB->get_records('adleradaptivity_tasks', array('adleradaptivity_id' => $instance_id));
        $adler_questions = [];
        foreach($adler_tasks as $task) {
            $adler_questions = array_merge($adler_questions, helpers::load_questions_by_task_id($task->id, true));
        }
        // perform deletion
        foreach ($adler_questions as $question) {
            $DB->delete_records('adleradaptivity_questions', array('id' => $question->id));
        }
        foreach ($adler_tasks as $task) {
            $DB->delete_records('adleradaptivity_tasks', array('id' => $task->id));
        }
        $DB->delete_records('adleradaptivity', array('id' => $instance_id));

        $transaction->allow_commit();
    } catch (Exception $e) {
        debugging('Could not delete adleradaptivity instance with id ' . $instance_id, E_ERROR);
        $transaction->rollback($e);
        return false;
    }

    return true;
}

function adleradaptivity_extend_settings_navigation(settings_navigation $settings, navigation_node $adleradaptivity_node) {
    global $CFG;

    require_once($CFG->libdir . '/questionlib.php');

//    if (has_capability('mod/adleradaptivity:edit', $settings->get_page()->context)) {
//        $url = new moodle_url('/mod/adleradaptivity/edit_questions.php', ['id' => $settings->get_page()->cm->id]);
//        $adleradaptivity_node->add(get_string('menu_edit_questions', 'adleradaptivity'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('t/edit', ''));
//    }

    question_extend_settings_navigation($adleradaptivity_node, $settings->get_page()->cm->context);
}

// Could not find out where this function is called (in 4.2.1). My guess is this was required before and is now obsolete with
// the introduction of the custom_completion class. Sadly, I could not find any documentation about this assumption.
///**
// * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
// *
// * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
// * @return array $descriptions the array of descriptions for the custom rules.
// */
//function mod_adleradaptivity_get_completion_active_rule_descriptions($cm) {
//    return ['Lorem ipsum'];
//}


/**
 * Add a get_coursemodule_info function to add 'extra' information
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function adleradaptivity_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    if (!$cm = $DB->get_record('adleradaptivity', $dbparams)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $cm->name;

//    // This populates the description field in the course overview
//    if ($coursemodule->showdescription) {
//        // Convert intro to html. Do not filter cached version, filters run at display time.
//        $result->content = format_module_intro('forum', $forum, $coursemodule->id, false);
//    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['default_rule'] = "blubbb";
    }
    return $result;
}
