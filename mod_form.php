<?php

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/adleradaptivity/lib.php');

class mod_adleradaptivity_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        $mform->addElement('text', 'name', get_string('form_field_title', 'mod_adleradaptivity'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('form_field_intro', 'mod_adleradaptivity'));


//        // Label does not add "Show description" checkbox meaning that 'intro' is always shown on the course page.
//        $mform->addElement('hidden', 'showdescription', 1);
//        $mform->setType('showdescription', PARAM_INT);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform = $this->_form;

        $group = [
            // Documentation of createElement:
            // First param is documented correctly in the function definition
            //* @param    $elementType         string     $elementType    type of element to add (text, textarea, file...)
            // documentation for the following params were found here: HTML_QuickForm_element -> __construct
            //* @param    $elementName=null    string     Name of the element
            //* @param    $elementLabel=null   mixed      Label(s) for the element
            //* @param    $attributes=null     mixed      Associative array of tag attributes or HTML attributes name="value" pairs
            $mform->createElement(
                'checkbox',
                'adleradaptivity_rule_checkbox',
                ' ',
                'Adler Adaptivity Logic (required, element will not fully work if this is not checked)'
            ),
            // This and the setType row together are an example for a number input field
//            $mform->createElement(
//                'text',
//                'unknown_property_2',
//                ' ',
//                ['size' => 3]
//            ),
        ];
//        $mform->setType('unknown_property_2', PARAM_INT);
        $mform->addGroup(
            $group,
            'adleradaptivity_rule_group',
            'Adaptivity rule',
            [' '],
            false
        );
        $mform->addHelpButton(
            'adleradaptivity_rule_group',
            'form_field_adleradaptivity_rule',  // translations key without _help postfix
            'adleradaptivity'  // translations component.
        );
//        $mform->disabledIf(
//            $this->get_suffixed_name('completionposts'),
//            $this->get_suffixed_name('completionpostsenabled'),
//            'notchecked'
//        );

        return ['adleradaptivity_rule_group'];
    }

    /**
     * @param array $data Input data not yet validated.
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        // for my understanding this should check whether the checkbox in add_completion_rules is checked
        return true;
    }

    function data_preprocessing(&$default_values) {
        $default_values['default_rule'] = true;
    }
}