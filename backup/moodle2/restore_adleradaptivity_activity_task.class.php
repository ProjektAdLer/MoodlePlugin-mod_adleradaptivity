<?php


/**
 * adleradaptivity restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */

require_once($CFG->dirroot . '/mod/adleradaptivity/backup/moodle2/restore_adleradaptivity_stepslib.php'); // Because it exists (must)

class restore_adleradaptivity_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // adleradaptivity only has one structure step
        $this->add_step(new restore_adleradaptivity_activity_structure_step('adleradaptivity_structure', 'adleradaptivity.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        // As I understand it, this describes where URLs are that have to be decoded (replaced/updated).
        // The replacement itself (the replacement rule) is described in define_decode_rules().
        // This method is the "where"
        // The method define_decode_rules() is the "how"
//        $contents[] = new restore_decode_content('adleradaptivity', array('intro'), 'adleradaptivity');

        return [];
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        return [];
    }

//    Seems like these methods are only relevant for the old "log" format, not for the newer event based logstore.
//    Therefore, there is no need to implement them.
//    public static function define_restore_log_rules() {}
//    public static function define_restore_log_rules_for_course() {}
}