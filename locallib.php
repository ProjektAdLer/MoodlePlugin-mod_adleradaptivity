<?php

use core\context\module;
use mod_adleradaptivity\event\course_module_viewed;


/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 * Taken from mod_quiz locallib.php
 *
 * I don't think this function with this exact name is not actually required to exist,
 * but it is common practice to do it like that.
 *
 * @param stdClass $adleradaptivity adleradaptivity object
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context_module $context context object
 */
function adleradaptivity_view(stdClass $adleradaptivity, stdClass $course, stdClass $cm, context_module $context): void {

    $params = [
        'objectid' => $adleradaptivity->id,
        'context' => $context
    ];

    $event = course_module_viewed::create($params);
    $event->add_record_snapshot('adleradaptivity', $adleradaptivity);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
