<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

# reference: quiz\processattempt.php

// Course module id.
$id = optional_param('id', 0, PARAM_INT);


// attempt id
$attemptid = required_param('attempt', PARAM_INT);


$timenow = time();

if ($id) {
    $cm = get_coursemodule_from_id('adleradaptivity', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('adleradaptivity', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('adleradaptivity', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

// Check login.
require_login($course, false, $cm);
require_sesskey();


$transaction = $DB->start_delegated_transaction();

//// get latest usage id
//// TODO: this is very very bad, but should work for the purposes of that prototype
//$entries = $DB->get_records('question_usages', null, 'id ASC');
//$attemptid = $entries[array_key_last($entries)]->id;


$quba = question_engine::load_questions_usage_by_activity($attemptid);

$questions = $DB->get_records('question');
//$mc_questions = array();
//foreach($questions as $key => $question) {
//    $question2 = question_bank::load_question($question->id);
////    $qtype = question_bank::get_qtype($question->qtype, false);
////    if ($qtype->name() === 'missingtype') {
////        debugging('Missing question type: ' . $question->qtype, E_WARNING);
////        continue;
////    }
////    if ($qtype->name() !== 'multichoice') {
////        debugging('Not a multichoice question: ' . $question->qtype, E_NOTICE);
////        continue;
////    }
////    $qtype->get_question_options($question);
//    $mc_questions[] = $question2;
//}
//
//// also done by question_bank::load_question
////$questions = [];
////foreach ($mc_questions as $questiondata) {
////    $questions[] = question_bank::make_question($questiondata);
////}
//
////// save attempt in db
////question_engine::save_questions_usage_by_activity($quba);
////// usage id
////$quba->get_id();
////// load attempt from db
////$quba = question_engine::load_questions_usage_by_activity($quba->get_id());
//
////$slot = $quba->get_first_question_number();
////$quba->get_slots();  // mh what is this could it simplify the code?
//$slots = [];
//foreach ($mc_questions as $mc_question) {
//    $slots[] = $quba->add_question($mc_question, 1);
//}



//    $postdata = $quba->extract_responses($slots, $_POST);
$quba->process_all_actions($timenow);
question_engine::save_questions_usage_by_activity($quba);

$transaction->allow_commit();

// check if key "finish" exists in $_POST
// if yes, redirect to result.php
// if no, redirect to view.php
if (array_key_exists('finish', $_POST)) {
    redirect(new moodle_url('/mod/adleradaptivity/result.php', ['id' => $id, 'attempt' => $attemptid]));
} else {
    redirect(new moodle_url('/mod/adleradaptivity/view.php', ['id' => $id, 'attempt' => $attemptid]));
}
