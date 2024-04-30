<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

# reference: quiz\processattempt.php

// TODO

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
// attempt id
$attemptid = required_param('attempt', PARAM_INT);


$timenow = time();

$cm = get_coursemodule_from_id('adleradaptivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('adleradaptivity', array('id' => $cm->instance), '*', MUST_EXIST);

// Check login.
require_login($course, false, $cm);
require_sesskey();

$transaction = $DB->start_delegated_transaction();

$quba = question_engine::load_questions_usage_by_activity($attemptid);

$quba->process_all_actions($timenow);



question_engine::save_questions_usage_by_activity($quba);

// Update completion state
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->update_state($cm, COMPLETION_COMPLETE);
}

$transaction->allow_commit();
redirect(new moodle_url('/mod/adleradaptivity/view.php', ['id' => $id, 'attempt' => $attemptid]));
