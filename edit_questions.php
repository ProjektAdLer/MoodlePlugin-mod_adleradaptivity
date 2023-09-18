<?php

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');

// -----------------------------------------------------
// This page is not working
// It is an additional page listed in the secondary menu in the module settings and could be used to edit questions
// -----------------------------------------------------


// Module can be referenced by either "id" (Course module id) or "a" (Activity instance id) parameter in GET request.
// Get Params from GET request.
$id = optional_param('id', 0, PARAM_INT);
$a = optional_param('a', 0, PARAM_INT);
// Get objects from either id or a
if ($id) {
    $cm = get_coursemodule_from_id('adleradaptivity', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('adleradaptivity', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

// Check if user is logged in and has access to this course module.
require_login($course, true, $cm);


$modulecontext = context_module::instance($cm->id);

// set page title, heading, url and context
$PAGE->set_url('/mod/adleradaptivity/edit_questions.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

//$output = $PAGE->get_renderer('mod_adleradaptivity', 'edit_questions_renderer');
$output = $PAGE->get_renderer('mod_adleradaptivity');
//ob_start(); // Start output buffering.
echo $output->header();

//echo $output->heading("BLUB");

echo $output->footer();
//ob_end_flush(); // Flush output buffers.
