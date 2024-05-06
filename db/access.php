<?php
/**
 * Capability definitions for the adleradaptivity module.
 *
 * @package    mod_adleradaptivity
 * @copyright  2023 Markus Heck <markus.heck@hs-kempten.de>
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/adleradaptivity:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
//            nobody is allowed to add the module as adding is not supported in moodle
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],
    'mod/adleradaptivity:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'mod/adleradaptivity:create_and_edit_own_attempt' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'mod/adleradaptivity:edit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
//            nobody is allowed to edit the module as editing is not supported in moodle
        ],
    ],
    'mod/adleradaptivity:view_and_edit_all_attempts' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
