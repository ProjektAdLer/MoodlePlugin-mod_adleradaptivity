<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

# reference: quiz\processattempt.php

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
// Activity instance id
$a = optional_param('a', 0, PARAM_INT);
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

$quba = question_engine::load_questions_usage_by_activity($attemptid);

$quba->process_all_actions($timenow);



// Update completion state
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->update_state($cm, COMPLETION_COMPLETE);
}

// check if key "finish" exists in $_POST
if (array_key_exists('finish', $_POST)) {
    $quba->finish_all_questions($timenow);
    question_engine::save_questions_usage_by_activity($quba);

    $transaction->allow_commit();


    // redirect to course
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
//    redirect(new moodle_url('/mod/adleradaptivity/result.php', ['id' => $id, 'attempt' => $attemptid]));
} else {
    question_engine::save_questions_usage_by_activity($quba);

    $transaction->allow_commit();
    redirect(new moodle_url('/mod/adleradaptivity/view.php', ['id' => $id, 'attempt' => $attemptid]));
}
