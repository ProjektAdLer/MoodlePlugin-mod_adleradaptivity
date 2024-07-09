<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_adleradaptivity_get_question_details' => array(         //web service function name
        'classname' => 'mod_adleradaptivity\external\get_question_details',  //class containing the external function OR namespaced class in classes/external/XXXX.php
        'description' => 'Get details for all questions in one adaptivity learning element',    //human readable description of the web service function
        'type' => 'read',                  //database rights of the web service function (read, write)
        'ajax' => false,        // is the service available to 'internal' ajax calls.
        'services' => array('adler_services'),   // Optional, only available for Moodle 3.1 onwards. List of built-in services (by shortname) where the function will be included.  Services created manually via the Moodle interface are not supported.
        'capabilities' => 'mod/adleradaptivity:view', // comma separated list of capabilities used by the function.
        'loginrequired' => true
    ),
    'mod_adleradaptivity_get_task_details' => array(
        'classname' => 'mod_adleradaptivity\external\get_task_details',
        'description' => 'Get details for all tasks in one adaptivity learning element',    //human readable description of the web service function
        'type' => 'read',
        'ajax' => false,
        'services' => array('adler_services'),
        'capabilities' => 'mod/adleradaptivity:view',
        'loginrequired' => true
    ),
    'mod_adleradaptivity_answer_questions' => array(
        'classname' => 'mod_adleradaptivity\external\answer_questions',
        'description' => 'Answer one question of an adaptivity learning element',    //human readable description of the web service function
        'type' => 'write',
        'ajax' => false,
        'services' => array('adler_services'),
        'capabilities' => 'mod/adleradaptivity:edit',
        'loginrequired' => true
    ),
);