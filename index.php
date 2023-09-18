<?php

// required file per moodle doc https://docs.moodle.org/dev/Activity_modules#index.php
// no idea where it is called (did not care about that yet)

require_once('../../config.php');

// The `id` parameter is the course id.
$id = required_param('id', PARAM_INT);

// Fetch the requested course.
$course = $DB->get_record('course', ['id'=> $id], '*', MUST_EXIST);

// Require that the user is logged into the course.
require_course_login($course);

$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('adleradaptivity') as $instanceid => $cm) {
    // Display information about your activity.
}