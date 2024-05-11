<?php

namespace mod_adleradaptivity\local;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use context_module;
use dml_exception;
use mod_adleradaptivity\local\db\adleradaptivity_attempt_repository;
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

    /** Get adleradaptivity questions with moodle question ids for the given adleradaptivity task id.
     *
     * @param int $task_id task id of the adleradaptivity task.
     * @param bool $ignore_question_version DANGER! whether to ignore the question version. If true,
     * the question version is not checked. Besides that questions with version != 1 are not supported by adleradaptivity,
     * deactivating this check might also result in the same question being returned multiple times in different versions.
     * This switch is intended only for stuff like module deletion.
     * @return array of objects with moodle question id and adleradaptivity question id.
     * @throws moodle_exception if any question version is not equal to 1.
     */
    public static function load_questions_by_task_id(int $task_id, bool $ignore_question_version = false) {
        global $DB;

        // Retrieves question versions from the {question_versions} table based on a specified adaptivity ID.
        $sql = "
            SELECT qv.questionid, qv.version, aq.*
            FROM {adleradaptivity_questions} aq
            JOIN {question_references} qr ON qr.itemid = aq.id
            JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
            
            WHERE aq.adleradaptivity_task_id = ?
            AND qr.component = 'mod_adleradaptivity'
            AND qr.questionarea = 'question';
        ";
        $question_data = $DB->get_records_sql($sql, [$task_id]);

        $result = [];
        foreach ($question_data as $one_question) {
            if (!$ignore_question_version && $one_question->version != 1) {
                throw new moodle_exception(
                    'question_version_not_one',
                    'mod_adleradaptivity',
                    '',
                    '',
                    'There is a question with version ' . $one_question->version . '. This is not supported by adleradaptivity.'
                );
            }

            // remove version field from $one_question
            unset($one_question->version);

            $result[] = $one_question;
        }

        return $result;
    }

    /** Get all question objects from the question table for the given course module ID (cmid) of the adleradaptivity element.
     *
     * @param int $cmid course module id of the adleradaptivity element.
     * @param bool $allow_shuffle whether to allow shuffling of questions. For adleradaptivity always false becaues the order of the answers in the 3D world (over api) is not under control of this plugin.
     * @return array of question_definition objects.
     * @throws moodle_exception if any question version is not equal to 1.
     */
    public static function load_questions_by_cmid($cmid, $allow_shuffle = false) {
        global $DB;

        // get instance id from cmid
        $instance_id = $DB->get_field('course_modules', 'instance', ['id' => $cmid]);

        // Retrieves question versions from the {question_versions} table based on a specified adaptivity ID.
        $sql = "
        SELECT qv.*
        FROM {adleradaptivity_questions} aq
        JOIN {question_references} qr ON qr.itemid = aq.id
        JOIN {adleradaptivity_tasks} at ON aq.adleradaptivity_task_id = at.id
        JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
        
        WHERE at.adleradaptivity_id = ?
        AND qr.component = 'mod_adleradaptivity'
        AND qr.questionarea = 'question'
        ";
        $question_versions = $DB->get_records_sql($sql, [$instance_id]);

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

    /** Get all tasks for the given instance ID (cmid) of the adleradaptivity element.
     *
     * @param int $cmid course module id of the adleradaptivity element.
     * @return array of task objects.
     * @throws dml_exception
     */
    public static function load_tasks_by_instance_id($instance_id) {
        global $DB;

        return $DB->get_records('adleradaptivity_tasks', ['adleradaptivity_id' => $instance_id]);
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
