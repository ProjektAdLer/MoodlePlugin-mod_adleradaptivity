<?php

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/adleradaptivity/lib.php');

class mod_adleradaptivity_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('text', 'name', get_string('form_field_title', 'mod_adleradaptivity'), ['size'=>'64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('form_field_intro', 'mod_adleradaptivity'));


//        // Label does not add "Show description" checkbox meaning that 'intro' is always shown on the course page.
//        $mform->addElement('hidden', 'showdescription', 1);
//        $mform->setType('showdescription', PARAM_INT);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

}