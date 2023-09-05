<?php

require('../../config.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'adleradaptivity');
$instance = $DB->get_record('adleradaptivity', ['id'=> $cm->instance], '*', MUST_EXIST);

global $PAGE, $OUTPUT;
require_course_login($course, true, $cm);



//require_once("../../config.php");
//
$id = optional_param('id',0,PARAM_INT);    // Course Module ID, or
//$l = optional_param('l',0,PARAM_INT);     // adleradaptivity ID
//
if ($id) {
    $PAGE->set_url('/mod/adleradaptivity/view.php', array('id' => $id));
//    if (! $cm = get_coursemodule_from_id('adleradaptivity', $id, 0, true)) {
//        throw new \moodle_exception('invalidcoursemodule');
//    }
//
//    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
//        throw new \moodle_exception('coursemisconf');
//    }
//
//    if (! $adleradaptivity = $DB->get_record("adleradaptivity", array("id"=>$cm->instance))) {
//        throw new \moodle_exception('invalidcoursemodule');
//    }

//} else {
//    $PAGE->set_url('/mod/adleradaptivity/view.php', array('l' => $l));
//    if (! $adleradaptivity = $DB->get_record("adleradaptivity", array("id"=>$l))) {
//        throw new \moodle_exception('invalidcoursemodule');
//    }
//    if (! $course = $DB->get_record("course", array("id"=>$adleradaptivity->course)) ){
//        throw new \moodle_exception('coursemisconf');
//    }
//    if (! $cm = get_coursemodule_from_instance("adleradaptivity", $adleradaptivity->id, $course->id, true)) {
//        throw new \moodle_exception('invalidcoursemodule');
//    }
}

echo $OUTPUT->footer();