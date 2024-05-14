<?php

namespace mod_adleradaptivity\local;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use context_module;
use dml_exception;
use mod_adleradaptivity\local\db\adleradaptivity_attempt_repository;
use mod_adleradaptivity\local\db\moodle_core_repository;
use moodle_exception;
use question_bank;
use question_engine;
use question_usage_by_activity;
use stdClass;

class helpers {
    /** Gets the attempt object (question usage aka $quba) for the given cm and given user.
     * If there is no attempt object for the given cm and user, a new attempt object is created.
     * If there is more than one attempt object for the given cm and user, an exception is thrown.
     *
     * @param int $cmid The course module ID of the adleradaptivity element.
     * @param int|null $userid The user ID; defaults to the current user if not provided.
     * @param bool $create_new_attempt If true, a new attempt is created if none exists yet.
     * @return false|question_usage_by_activity
     * @throws dml_exception
     * @throws moodle_exception If multiple question usages are found for the given criteria.
     */
    public static function load_or_create_question_usage(int $cmid, int|null $userid = null, bool $create_new_attempt = true): false|question_usage_by_activity {
        global $USER;
        $adleradaptivity_attempt_repository = new adleradaptivity_attempt_repository();

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Fetch existing question usages for the given cmid and userid
        $adleradaptivity_attempts_all_users = $adleradaptivity_attempt_repository->get_adleradaptivity_attempt_by_cmid($cmid);
        // filter the results by userid
        $adleradaptivity_attempts = array_filter($adleradaptivity_attempts_all_users, function ($attempt) use ($userid) {
            return $attempt->user_id == $userid;
        });


        switch (count($adleradaptivity_attempts)) {
            case 0:
                // No existing usage found, so generate a new one, except if $create_new_attempt is false.
                if (!$create_new_attempt) {
                    return false;
                }

                $quba = static::generate_new_attempt($cmid);
                $attempt_id = $quba->get_id();

                $adleradaptivity_attempt = new stdClass();
                $adleradaptivity_attempt->attempt_id = $attempt_id;
                $adleradaptivity_attempt->user_id = $userid;

                $adleradaptivity_attempt_repository = new adleradaptivity_attempt_repository();
                $adleradaptivity_attempt_repository->create_adleradaptivity_attempt($adleradaptivity_attempt);
                break;
            case 1:
                // One usage found, so load it.
                $attempt_id = reset($adleradaptivity_attempts)->attempt_id;
                $quba = question_engine::load_questions_usage_by_activity($attempt_id);
                break;
            default:
                // Multiple usages found; this is not supported, so throw an exception.
                throw new moodle_exception('too_many_attempts', 'mod_adleradaptivity', '', '', 'There is more than one attempt for cmid ' . $cmid . ' and userid ' . $userid . '. This is not supported by adleradaptivity.');
        }

        return $quba;
    }


    /**
     *  Generate a new question attempt (question usage) for a given course module ID.
     *  Note that question usages are user independent.
     *
     * @param int $cmid The course module ID of the adleradaptivity element.
     * @return question_usage_by_activity The generated question usage object.
     * @throws moodle_exception
     */
    public static function generate_new_attempt($cmid) {
        // Retrieve the module context
        $modulecontext = context_module::instance($cmid);

        // Create a new question usage
        $quba = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $modulecontext);
        $quba->set_preferred_behaviour("adaptivenopenalty");

        // Load the associated questions and add them to the usage
        $questions = static::load_questions_by_cmid($cmid);
        foreach ($questions as $question) {
            $quba->add_question($question, 1);
        }

        // Initialize all questions within the usage
        $quba->start_all_questions();

        // Persist the question usage
        question_engine::save_questions_usage_by_activity($quba);

        return $quba;
    }

    /** Get all question objects from the question table for the given course module ID (cmid) of the adleradaptivity element.
     *
     * @param int $cmid course module id of the adleradaptivity element.
     * @param bool $allow_shuffle whether to allow shuffling of questions. For adleradaptivity always false becaues the order of the answers in the 3D world (over api) is not under control of this plugin.
     * @return array of question_definition objects.
     * @throws moodle_exception if any question version is not equal to 1.
     */
    public static function load_questions_by_cmid(int $cmid, bool $allow_shuffle = false) {
        $moodle_core_repository = new moodle_core_repository();

        // get instance id from cmid
        $instance_id = get_coursemodule_from_id('', $cmid)->instance;

        // Retrieves question versions from the {question_versions} table based on a specified adaptivity ID.
        $question_versions = $moodle_core_repository->get_question_versions_by_adleradaptivity_instance_id($instance_id);

        $questions = [];
        foreach ($question_versions as $question_version) {
            if ($question_version->version != 1) {
                throw new moodle_exception(
                    'question_version_not_one',
                    'mod_adleradaptivity',
                    '',
                    '',
                    'There is a question with version ' . $question_version->version . '. This is not supported by adleradaptivity.'
                );
            }

            $questions[] = question_bank::load_question($question_version->questionid, $allow_shuffle);
        }

        return $questions;
    }

    /** Get slot number by question uuid from question_engine
     *
     * @param string $uuid uuid of the adleradaptivity question.
     * @param question_usage_by_activity $quba question usage object.
     * @return int slot number
     * @throws moodle_exception if question is not found in question usage
     */
    public static function get_slot_number_by_uuid(string $uuid, question_usage_by_activity $quba) {
        foreach ($quba->get_slots() as $slot) {
            if ($quba->get_question($slot)->idnumber == $uuid) {
                return $slot;
            }
        }
        throw new moodle_exception('Question with uuid ' . $uuid . ' not found in question usage');
    }
}
