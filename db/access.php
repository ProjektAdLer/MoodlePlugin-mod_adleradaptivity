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
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
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
];
