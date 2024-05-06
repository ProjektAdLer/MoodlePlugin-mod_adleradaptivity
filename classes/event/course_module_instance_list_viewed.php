<?php
namespace mod_adleradaptivity\event;

use core\event\course_module_instance_list_viewed as core_event_course_module_instance_list_viewed;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_adleradaptivity instance list viewed event.
 *
 * @package    mod_adleradaptivity
 */
class course_module_instance_list_viewed extends core_event_course_module_instance_list_viewed {
    // No code required here as the parent class handles it all.
}
