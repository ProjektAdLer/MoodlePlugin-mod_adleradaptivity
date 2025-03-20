<?php

namespace mod_adleradaptivity\privacy;

use coding_exception;
use context;
use context_module;
use core\di;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as privacy_metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper as privacy_helper;
use core_privacy\local\request\plugin\provider as privacy_request_provider;
use core_privacy\local\request\core_userlist_provider as privacy_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_question\privacy\provider as question_privacy_provider;
use dml_exception;
use mod_adleradaptivity\local\completion_helpers;
use moodle_database;
use qubaid_join;
use question_display_options;
use question_engine;

class provider implements privacy_metadata_provider, privacy_request_provider, privacy_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        // Using core_question for all question functionality
        $collection->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        $collection->add_database_table(
            'adleradaptivity_attempts',
            [
                'user_id' => 'privacy:metadata:adleradaptivity_attempts:user_id',
                'attempt_id' => 'privacy:metadata:adleradaptivity_attempts:attempt_id',
            ],
            'privacy:metadata:adleradaptivity_attempts'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Users who attempted the activity
        $sql = "SELECT c.id
            FROM {context} c
            JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            JOIN {adleradaptivity_attempts} aa ON aa.adleradaptivity_id = cm.instance
            WHERE aa.user_id = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'adleradaptivity',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $params = [
            'modname' => 'adleradaptivity',
            'cmid' => $context->instanceid,
        ];

        // Users who attempted the activity
        $sql = "SELECT aa.user_id
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            JOIN {adleradaptivity_attempts} aa ON aa.attempt_id = cm.instance
            WHERE cm.id = :cmid";
        $userlist->add_from_sql('user_id', $sql, $params);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // there is a shorter way over question_usage table, but this is the way it is done in mod_quiz and it's likely to be more error resistant
        $sql = "SELECT a.*
            FROM {context} c
            JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            JOIN {adleradaptivity} a ON a.id = cm.instance
            JOIN {adleradaptivity_attempts} aa ON aa.adleradaptivity_id = a.id AND aa.user_id = :userid
            WHERE c.id {$contextsql}";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'adleradaptivity',
            'userid' => $userid
        ];
        $params += $contextparams;

        $adleradaptivities = $DB->get_recordset_sql($sql, $params);


        foreach ($adleradaptivities as $adleradaptivity) {
            $cm = get_coursemodule_from_instance('adleradaptivity', $adleradaptivity->id, 0, false, MUST_EXIST);
            $context = context_module::instance($cm->id);
            $adleradaptivity_data = privacy_helper::get_context_data(
                $context,
                $contextlist->get_user()
            );
            privacy_helper::export_context_files($context, $contextlist->get_user());

            if (!empty($adleradaptivity_data->intro)) {
                $adleradaptivity_data->intro = format_string($adleradaptivity_data->intro);
            }

            if (!empty($adleradaptivity_data->completion)) {
                $adleradaptivity_data->completion = get_string(
                    'privacy:export:attempt:' . ($adleradaptivity_data->completion->state === 1 ? 'completed' : 'not_completed'),
                    'mod_adleradaptivity'
                );
            }

            writer::with_context($context)->export_data([], $adleradaptivity_data);
        }

        $adleradaptivities->close();

        static::export_attempts($contextlist);
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function export_attempts(approved_contextlist $contextlist) {
        $user_id = $contextlist->get_user()->id;

        // This is a simpler version as the commented out code below.
        $sql = "SELECT c.id AS contextid,
                       cm.id AS cmid,
                       a.id as adleradaptivity_id,
                       aa.*
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adleradaptivity'
                  JOIN {adleradaptivity} a ON a.id = cm.instance
                  JOIN {adleradaptivity_attempts} aa ON aa.adleradaptivity_id = a.id AND aa.user_id = :aauserid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'aauserid' => $user_id,
        ];


        // This code is adapted from mod_quiz. It is fully working but very complex. As i am unsure if i might
        // overlook some important details, i will leave it here for now.
//        $qubaid1 = question_privacy_provider::get_related_question_usages_for_user(
//            'rel1',
//            'mod_adleradaptivity',
//            'aa.attempt_id',
//            $user_id
//        );
//        $qubaid2 = question_privacy_provider::get_related_question_usages_for_user(
//            'rel2',
//            'mod_adleradaptivity',
//            'aa.attempt_id',
//            $user_id
//        );
//
//        $sql = "SELECT
//                    c.id AS contextid,
//                    cm.id AS cmid,
//                    a.id as adleradaptivity_id,
//                    aa.*
//                  FROM {context} c
//                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel1
//                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adleradaptivity'
//                  JOIN {adleradaptivity} a ON a.id = cm.instance
//                  JOIN {question_usages} qu ON qu.contextid = c.id
//                  JOIN {adleradaptivity_attempts} aa ON aa.attempt_id = qu.id
//            " . $qubaid1->from . "
//                 WHERE aa.user_id = :aauserid
//                 UNION
//                SELECT
//                    c.id AS contextid,
//                    cm.id AS cmid,
//                    a.id as adleradaptivity_id,
//                    aa.*
//                  FROM {context} c
//                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel2
//                  JOIN {modules} m ON m.id = cm.module AND m.name = 'adleradaptivity'
//                  JOIN {adleradaptivity} a ON a.id = cm.instance
//                  JOIN {question_usages} qu ON qu.contextid = c.id
//                  JOIN {adleradaptivity_attempts} aa ON aa.attempt_id = qu.id
//            " . $qubaid2->from . "
//                 WHERE " . $qubaid2->where() . "
//        ";
//
//        $params = array_merge(
//            [
//                'contextlevel1' => CONTEXT_MODULE,
//                'contextlevel2' => CONTEXT_MODULE,
//                'aauserid' => $user_id,
//            ],
//            $qubaid1->from_where_params(),
//            $qubaid2->from_where_params(),
//        );


        $attempts = di::get(moodle_database::class)->get_recordset_sql($sql, $params);

        $attempt_number = 0;
        foreach ($attempts as $attempt) {
            $context = context_module::instance($attempt->cmid);

            $attemptsubcontext = [
                get_string('privacy:export:attempt', 'mod_adleradaptivity'),
                ++$attempt_number
            ];
            $options = new question_display_options();

            // Store the question usage data.
            question_privacy_provider::export_question_usage($user_id,
                $context,
                $attemptsubcontext,
                $attempt->attempt_id,
                $options,
                true
            );

            $quba = question_engine::load_questions_usage_by_activity($attempt->attempt_id);
            $completed = completion_helpers::check_module_completed($quba, $attempt->adleradaptivity_id);

            // Store the quiz attempt data.
            $data = (object)[
                'state' => get_string(
                    'privacy:export:attempt:' . ($completed ? 'completed' : 'not_completed'),
                    'mod_adleradaptivity'
                ),
            ];


            writer::with_context($context)->export_data($attemptsubcontext, $data);

        }
        $attempts->close();
    }


    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('adleradaptivity', $context->instanceid);
        if (!$cm) {
            // Is not an adleradaptivity module.
            return;
        }

        $adleradaptivity = di::get(moodle_database::class)->get_record('adleradaptivity', ['id' => $cm->instance], '*', MUST_EXIST);

        di::get(moodle_database::class)->delete_records('adleradaptivity_attempts', ['adleradaptivity_id' => $adleradaptivity->id]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $db = di::get(moodle_database::class);

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('adleradaptivity', $context->instanceid, 0, false, MUST_EXIST);
            if (!$cm) {
                // Not an adleradaptivity module or no record found.
                continue;
            }

            $adleradaptivity = $db->get_record('adleradaptivity', ['id' => $cm->instance], '*', MUST_EXIST);

            // Delete all question usage data for the user.
            $qubaids = new qubaid_join(
                '{adleradaptivity_attempts} aa',
                'aa.attempt_id',
                'aa.adleradaptivity_id = :adlerid AND aa.user_id = :userid',
                [
                    'adlerid' => $adleradaptivity->id,
                    'userid' => $userid
                ]
            );
            question_engine::delete_questions_usage_by_activities($qubaids);

            // Delete all adleradaptivity attempts for the user.
            $db->delete_records('adleradaptivity_attempts', [
                'adleradaptivity_id' => $adleradaptivity->id,
                'user_id' => $userid,
            ]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        $db = di::get(moodle_database::class);
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('adleradaptivity', $context->instanceid);
        if (!$cm) {
            // Not an adleradaptivity module.
            return;
        }

        $adleradaptivity = $db->get_record('adleradaptivity', ['id' => $cm->instance], '*', MUST_EXIST);

        list($userinsql, $userinparams) = $db->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['adlerid' => $adleradaptivity->id], $userinparams);

        // Delete all question usage data for the users.
        $qubaids = new qubaid_join(
            '{adleradaptivity_attempts} aa',
            'aa.attempt_id',
            "aa.adleradaptivity_id = :adlerid AND aa.user_id {$userinsql}",
            $params
        );
        question_engine::delete_questions_usage_by_activities($qubaids);

        // Delete all adleradaptivity attempts for the users.
        $db->delete_records_select('adleradaptivity_attempts', "adleradaptivity_id = :adlerid AND user_id {$userinsql}", $params);
    }
}