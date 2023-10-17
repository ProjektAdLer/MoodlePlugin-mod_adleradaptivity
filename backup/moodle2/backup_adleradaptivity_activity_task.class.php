<?php

require_once($CFG->dirroot . '/mod/adleradaptivity/backup/moodle2/backup_adleradaptivity_stepslib.php'); // Because it exists (must)

/**
 * adleradaptivity backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_adleradaptivity_activity_task extends backup_activity_task {

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
        $this->add_step(new backup_adleradaptivity_activity_structure_step('adleradaptivity_structure', 'adleradaptivity.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        return $content;
    }
}