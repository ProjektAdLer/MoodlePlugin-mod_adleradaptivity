<?php

namespace mod_adleradaptivity\local;

require_once($CFG->libdir . '/questionlib.php');

//function adleradaptivity_add_question($question_id, $module, $db=null) {
//    if ($db == null) {
//        global $DB;
//        $db = $DB;
//    }
//
//    if (!isset($quiz->cmid)) {
//        $module = get_coursemodule_from_instance('adleradaptivity', $module->id, $module->course);
//    }
//
//    // Make sue the question is of the "multichoice" type, other types are not supported by adler adaptivity
//    $questiontype = $DB->get_field('question', 'qtype', ['id' => $question_id]);
//    if ($questiontype != 'multichoice') {
//        throw new coding_exception(
//            'Question with id ' . $question_id . ' is not of type "multichoice", but "' . $questiontype . '"'
//        );
//    }
//
//    $transaction = $db->start_delegated_transaction();
//
//
//}
use context_module;
use dml_exception;
use dml_missing_record_exception;
use moodle_exception;
use question_bank;
use question_engine;
use question_usage_by_activity;
use stdClass;

class helpers {
    /** Load adleradaptivity_attempts by cmid
     * - Get context by cmid
     * - with the context id get the question_usage
     * - question_usage and adleradaptivity_attempts are a 1:1 relation
     *
     * @param int $cmid The course module ID of the adleradaptivity element.
     * @return array adleradaptivity_attempt The question usage object.
     * @throws dml_exception
     * @throws dml_missing_record_exception If the expected records are not found.
     */
    public static function load_adleradaptivity_attempt_by_cmid($cmid) {
        global $DB;

        // Get the context for the provided course module ID.
        $modulecontext = context_module::instance($cmid);

        // Create SQL to join adleradaptivity_attempts with question_usages based on context ID
        $sql = "
        SELECT aa.*
        FROM `{adleradaptivity_attempts}` AS aa
        JOIN `{question_usages}` AS qu ON qu.id = aa.attempt_id
        WHERE qu.contextid = ?
    ";
        return $DB->get_records_sql($sql, [$modulecontext->id]);
    }

    /** Gets the attempt object (question usage aka $quba) for the given cm and given user.
     * If there is no attempt object for the given cm and user, a new attempt object is created.
     * If there is more than one attempt object for the given cm and user, an exception is thrown.
     *
     * @param int $cmid The course module ID of the adleradaptivity element.
     * @param int|null $userid The user ID; defaults to the current user if not provided.
     * @return question_usage_by_activity The question usage object.
     * @throws dml_exception
     * @throws moodle_exception If multiple question usages are found for the given criteria.
     */
    public static function load_or_create_question_usage($cmid, $userid = null) {
        global $DB, $USER;

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Fetch existing question usages for the given cmid and userid
        $adleradaptivity_attempts_all_users = static::load_adleradaptivity_attempt_by_cmid($cmid);
        // filter the results by userid
        $adleradaptivity_attempts = array_filter($adleradaptivity_attempts_all_users, function ($attempt) use ($userid) {
            return $attempt->user_id == $userid;
        });


        switch (count($adleradaptivity_attempts)) {
            case 0:
                // No existing usage found, so generate a new one.
                $quba = static::generate_new_attempt($cmid);
                $attempt_id = $quba->get_id();

                $adleradaptivity_attempt = new stdClass();
                $adleradaptivity_attempt->attempt_id = $attempt_id;
                $adleradaptivity_attempt->user_id = $userid;
                $DB->insert_record('adleradaptivity_attempts', $adleradaptivity_attempt);
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
    public static function load_questions_by_cmid($cmid, $allow_shuffle=false) {
        global $DB;

        // get instance id from cmid
        $instance_id = $DB->get_field('course_modules', 'instance', ['id' => $cmid]);

        // Retrieves question versions from the `{question_versions}` table based on a specified adaptivity ID.
        // This is achieved by:
        // 1. Joining `{adleradaptivity_questions}` with `{adleradaptivity_tasks}`
        // to filter questions associated with a specific task.
        // 2. Further joining with `{question_versions}` to get the versions
        // of questions that match the adaptivity task's criteria.
        $sql = "
        SELECT qv.*
        FROM `{adleradaptivity_questions}` AS qa
        JOIN `{adleradaptivity_tasks}` AS at ON qa.adleradaptivity_tasks_id = at.id
        JOIN `{question_versions}` AS qv ON qv.questionbankentryid = qa.question_bank_entries_id
        WHERE at.adleradaptivity_id = ?
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

    /** Get question object from the question table for the given adleradaptivity question uuid.
     *
     * @param string $uuid uuid of the adleradaptivity question.
     * @return stdClass question object.
     * @throws moodle_exception if question version is not equal to 1.
     * @throws dml_exception if question is not found or there are multiple results for the same question (multiple versions)
     */
    public static function load_question_by_uuid($uuid) {
        global $DB;


        $sql = "
            SELECT q.*, aaq.*, qv.version, qmo.single
            FROM {question_bank_entries} as qbe
            JOIN {question_versions} as qv ON qv.questionbankentryid = qbe.id
            JOIN {question} as q ON q.id = qv.questionid
            JOIN {adleradaptivity_questions} as aaq ON aaq.question_bank_entries_id = qbe.id
            JOIN {qtype_multichoice_options} as qmo ON qmo.questionid = q.id
            WHERE qbe.idnumber = :question_uuid
        ";
        $question_versions = $DB->get_records_sql(
            $sql,
            [
                'question_uuid' => $uuid
            ]
        );

        if (count($question_versions) == 0) {
            throw new dml_exception('Question with uuid ' . $uuid . ' not found');
        }

        if (count($question_versions) > 1) {
            throw new dml_exception('Multiple questions with uuid ' . $uuid . ' found. This might be related to different versions of the same question. This is not supported by this module');
        }

        $question = reset($question_versions);

        if ($question->version != 1) {
            throw new moodle_exception(
                'question_version_not_one',
                'mod_adleradaptivity',
                '',
                '',
                'There question version is ' . $question->version . '. It should be one.'
            );
        }

        // Limiting fields returned because I dont know yet what this method exactly has to return. If this even the right type or if
        // I should better return a question_definition object.
        // Limiting fields allow me to easier see what data is actually used
        $result = new stdClass();
        $result->id = $question->id;
        $result->qtype = $question->qtype;
        $result->single = $question->single;
        return $result;
    }

    /** Get slot number by question uuid from question_engine
     *
     * @param string $uuid uuid of the adleradaptivity question.
     * @param question_usage_by_activity $quba question usage object.
     * @return int slot number
     * @throws moodle_exception if question is not found in question usage
     */
    public static function get_slot_number_by_uuid($uuid, $quba) {
        foreach ($quba->get_slots() as $slot) {
            if ($quba->get_question($slot)->idnumber == $uuid) {
                return $slot;
            }
        }
        throw new moodle_exception('Question with uuid ' . $uuid . ' not found in question usage');
    }
}
