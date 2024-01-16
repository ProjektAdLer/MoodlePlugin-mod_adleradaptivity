<?php

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_adleradaptivity_mod_form extends moodleform_mod {
    function definition() {
        $mform =& $this->_form;
        $mform->addElement('static', '', '',
            html_writer::tag('h1', get_string('editing_not_supported', 'adleradaptivity')));
        $this->standard_coursemodule_elements();
    }
}