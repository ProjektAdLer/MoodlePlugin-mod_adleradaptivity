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
        $contents = array();

        // As I understand it, this describes where URLs are that have to be decoded (replaced/updated).
        // The replacement itself (the replacement rule) is described in define_decode_rules().
        // This method is the "where"
        // The method define_decode_rules() is the "how"
//        $contents[] = new restore_decode_content('adleradaptivity', array('intro'), 'adleradaptivity');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        return [];
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * adleradaptivity logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        // TODO
//        $rules[] = new restore_log_rule('adleradaptivity', 'add', 'view.php?id={course_module}', '{adleradaptivity}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'update', 'view.php?id={course_module}', '{adleradaptivity}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'view', 'view.php?id={course_module}', '{adleradaptivity}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'choose', 'view.php?id={course_module}', '{adleradaptivity}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'choose again', 'view.php?id={course_module}', '{adleradaptivity}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'report', 'report.php?id={course_module}', '{adleradaptivity}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        // TODO
//        // Fix old wrong uses (missing extension)
//        $rules[] = new restore_log_rule('adleradaptivity', 'view all', 'index?id={course}', null,
//            null, null, 'index.php?id={course}');
//        $rules[] = new restore_log_rule('adleradaptivity', 'view all', 'index.php?id={course}', null);

        return $rules;
    }

}