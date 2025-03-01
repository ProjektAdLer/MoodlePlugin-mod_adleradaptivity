<?php

use core\di;

function xmldb_adleradaptivity_upgrade($oldversion) {
    $db = di::get(moodle_database::class);
    $dbman = di::get(moodle_database::class)->get_manager();

    if ($oldversion < 2024102108) {
        $table = new xmldb_table('adleradaptivity_attempts');
        $field = new xmldb_field('adleradaptivity_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, "0", 'user_id');  // default value is garbage and only used as temporary value for the upgrade
        $key = new xmldb_key('fk_adleradaptivity_id', XMLDB_KEY_FOREIGN, ['adleradaptivity_id'], 'adleradaptivity', ['id']);

        // Conditionally launch add field adleradaptivity_id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $dbman->add_key($table, $key);

        $sql = "UPDATE {adleradaptivity_attempts} aa
            JOIN {question_usages} qu ON qu.id = aa.attempt_id
            JOIN {context} c ON c.id = qu.contextid
            SET aa.adleradaptivity_id = c.instanceid";
        $db->execute($sql);

        upgrade_mod_savepoint(true, 2024102108, 'adleradaptivity');
    }

    return true;
}