<?php
/**
 * Prints an instance of mod_adleradaptivity.
 *
 * @package     mod_adleradaptivity
 * @copyright   2023 Markus Heck
 */

use qbank_previewquestion\question_preview_options;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir . '/questionlib.php');


// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$a = optional_param('a', 0, PARAM_INT);

// attempt id
$attemptid = optional_param('attempt', -1, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('adleradaptivity', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('adleradaptivity', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);


// This probably has to be implemented, but not here and not that way. search for "course_module_viewed" in other modules
//$event = \mod_adleradaptivity\event\course_module_viewed::create(array(
//    'objectid' => $moduleinstance->id,
//    'context' => $modulecontext
//));
//$event->add_record_snapshot('course', $course);
//$event->add_record_snapshot('adleradaptivity', $moduleinstance);
//$event->trigger();

$PAGE->set_url('/mod/adleradaptivity/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);



// load quiz attempt if attempt parameter is not -1, otherwise create new attempt
if ($attemptid === -1) {
    $quba = question_engine::make_questions_usage_by_activity('mod_adleradaptivity', $modulecontext);
    $quba->set_preferred_behaviour("adaptivenopenalty");
    $questions = array();
    foreach($DB->get_records('question') as $key => $question) {
        $questions[] = question_bank::load_question($question->id, false);
    }

    $slots = [];
    foreach ($questions as $question) {
        $slots[] = $quba->add_question($question, 1);
    }
    //$slots = $quba->get_slots();  // this should be equivalent to defining the slots array from the return values of add_question()

    //$quba->start_question($slot, $options->variant); // should be an alternative for individual questions to start_all_questions()
    $quba->start_all_questions();

    question_engine::save_questions_usage_by_activity($quba);
} else {
    $quba = question_engine::load_questions_usage_by_activity($attemptid);
    $slots = $quba->get_slots();
}


// not exactly sure about that, probably better doing this in the foreach loop below
$question = $questions[0];
$options = new question_preview_options($question);
$options->load_user_defaults();
$options->set_from_request();


$actionurl = new moodle_url('/mod/adleradaptivity/processattempt.php', ['id' => $cm->id, 'attempt' => $quba->get_id()]);


$displaynumber = 1;

echo $OUTPUT->header();

// Start the question form.
echo html_writer::start_tag('form', array('method' => 'post', 'action' => $actionurl,
    'enctype' => 'multipart/form-data', 'id' => 'responseform'));
echo html_writer::start_tag('div');
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots', 'value' => json_encode($slots)));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos', 'value' => '', 'id' => 'scrollpos'));
echo html_writer::end_tag('div');

// Output the question.
foreach ($slots as $slot) {
    echo $quba->render_question($slot, $options, round($displaynumber/2) . ($slot % 2 == 1 ? "a" : "b"));
    $displaynumber++;
}


// Finish the question form.
echo html_writer::start_tag('div', array('id' => 'previewcontrols', 'class' => 'controls'));
//echo html_writer::empty_tag('input', array('type' => 'submit',
//        'name' => 'restart', 'value' => get_string('restart', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
//        'id' => 'id_restart_question_preview'));
//echo html_writer::empty_tag('input', array('type' => 'submit',
//        'name' => 'save', 'value' => get_string('save', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
//        'id' => 'id_save_question_preview'));
//echo html_writer::empty_tag('input', array('type' => 'submit',
//        'name' => 'fill',    'value' => get_string('fillincorrect', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1'));
echo html_writer::empty_tag('input', array('type' => 'submit',
        'name' => 'finish', 'value' => get_string('submitandfinish', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
        'id' => 'id_finish_question_preview'));
echo html_writer::end_tag('div');
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
