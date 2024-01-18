<?php
global $USER;

/**
 * Prints an instance of mod_adleradaptivity.
 *
 * @package     mod_adleradaptivity
 * @copyright   2023 Markus Heck
 */

use mod_adleradaptivity\external\external_helpers;
use mod_adleradaptivity\local\helpers;
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
    $module_instance = $DB->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $module_instance = $DB->get_record('adleradaptivity', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $module_instance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('adleradaptivity', $module_instance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$module_context = context_module::instance($cm->id);


// This probably has to be implemented, but not here and not that way. search for "course_module_viewed" in other modules
//$event = \mod_adleradaptivity\event\course_module_viewed::create(array(
//    'objectid' => $moduleinstance->id,
//    'context' => $modulecontext
//));
//$event->add_record_snapshot('course', $course);
//$event->add_record_snapshot('adleradaptivity', $moduleinstance);
//$event->trigger();

$PAGE->set_url('/mod/adleradaptivity/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($module_instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($module_context);



// load quiz attempt if attempt parameter is not -1, otherwise create new attempt
if ($attemptid === -1) {
    $quba = helpers::load_or_create_question_usage($cm->id);
//    question_engine::save_questions_usage_by_activity($quba);
} else {
    // If this page is called with an attemptid, then it is required to check if the user is allowed to edit this attempt.
    // Only users with the capability 'mod/adleradaptivity:edit_all_attempts' are allowed to edit all attempts.
    $attempt = $DB->get_record('adleradaptivity_attempts', array('attempt_id' => $attemptid));
    if ($attempt->user_id != $USER->id) {
        // validate if current user is allowed to edit this attempt because he has the capability 'mod/adleradaptivity:edit_all_attempts'
        require_capability('mod/adleradaptivity:edit_all_attempts', $module_context);
    }

    // The user is allowed to edit this attempt, so load the attempt.
    $quba = question_engine::load_questions_usage_by_activity($attemptid);
}
$slots = $quba->get_slots();
// load all questions to fill $questions variable
$questions = array();
foreach($slots as $slot) {
    $question = $quba->get_question($slot);
    $adaptivity_question = helpers::get_adleradaptivity_question_by_question_bank_entries_id($question->questionbankentryid);
    $questions[] = [
        'question' => $quba->get_question($slot),
        'slot' => $slot,
        'adaptivity_question' => $adaptivity_question,
        'task' => helpers::get_task_by_question_uuid($question->idnumber, $module_instance->id)
    ];
}

// order questions by task and difficulty
usort($questions, function($a, $b) {
    if ($a['task']->id == $b['task']->id) {
        return $a['adaptivity_question']->difficulty <=> $b['adaptivity_question']->difficulty;
    }
    return $a['task']->id <=> $b['task']->id;
});


// not exactly sure about that, probably better doing this in the foreach loop below
$question = $questions[0]['question'];
$options = new question_preview_options($question);
$options->load_user_defaults();
$options->set_from_request();


$actionurl = new moodle_url('/mod/adleradaptivity/processattempt.php', ['id' => $cm->id, 'attempt' => $quba->get_id()]);



echo $OUTPUT->header();

// Start the question form.
echo html_writer::start_tag('form', array('method' => 'post', 'action' => $actionurl,
    'enctype' => 'multipart/form-data', 'id' => 'responseform'));
echo html_writer::start_tag('div');
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots', 'value' => json_encode($slots)));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos', 'value' => '', 'id' => 'scrollpos'));
echo html_writer::end_tag('div');

// enum with values: 0 = easy, 100 = normal, 200 = hard
$difficulties = [
    0 => 'easy',
    100 => 'normal',
    200 => 'hard'
];


//$displaynumber = 1;

$prev_task = null;
// Output the question.
foreach ($questions as $question) {
    if ($prev_task === null || $prev_task->id !== $question['task']->id) {
        // add a heading
        echo html_writer::start_tag('h3');
        echo $question['task']->title;
        echo " ";
        echo $question['task']->required_difficulty === null ? "optional" : "required difficulty: " . $difficulties[$question['task']->required_difficulty];
        echo " ";

        echo html_writer::end_tag('h3');
        // horizontal line
        echo html_writer::empty_tag('hr');
        $prev_task = $question['task'];
    }
//    // add a heading
//    echo html_writer::start_tag('h3');
//    echo "Task x";
//    echo html_writer::end_tag('h3');
//    // horizontal line
//    echo html_writer::empty_tag('hr');
//    echo $quba->render_question($slot, $options, round($displaynumber/2) . ($slot % 2 == 1 ? "a" : "b"));
    echo $quba->render_question($question['slot'], $options, $difficulties[$question['adaptivity_question']->difficulty]);
//    echo $quba->render_question($question['slot'], $options, $displaynumber);
//    $displaynumber++;
}


//// Finish the question form.
//echo html_writer::start_tag('div', array('id' => 'previewcontrols', 'class' => 'controls'));
////echo html_writer::empty_tag('input', array('type' => 'submit',
////        'name' => 'restart', 'value' => get_string('restart', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
////        'id' => 'id_restart_question_preview'));
////echo html_writer::empty_tag('input', array('type' => 'submit',
////        'name' => 'save', 'value' => get_string('save', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
////        'id' => 'id_save_question_preview'));
////echo html_writer::empty_tag('input', array('type' => 'submit',
////        'name' => 'fill',    'value' => get_string('fillincorrect', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1'));
//echo html_writer::empty_tag('input', array('type' => 'submit',
//        'name' => 'finish', 'value' => get_string('submitandfinish', 'question'), 'class' => 'btn btn-secondary mr-1 mb-1',
//        'id' => 'id_finish_question_preview'));
//echo html_writer::end_tag('div');
//echo html_writer::end_tag('form');

echo $OUTPUT->footer();
