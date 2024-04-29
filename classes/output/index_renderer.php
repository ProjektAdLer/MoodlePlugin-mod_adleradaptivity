<?php

namespace mod_adleradaptivity\output;

use moodle_url;
use plugin_renderer_base;
use stdClass;

class index_renderer extends plugin_renderer_base {
    public function render_page(stdClass $course): string {
        // Get all the appropriate data.
        if (!$adleradaptivities = get_all_instances_in_course('adleradaptivity', $course)) {
            notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'adleradaptivity')), "../../course/view.php?id=$course->id");
            die;
        }

        // Prepare the data for the Mustache template.
        $data = [
            'module_name_plural' => get_string('modulenameplural', 'adleradaptivity'),
            'course_format_uses_sections' => course_format_uses_sections($course->format),
            'column_title_section' => course_format_uses_sections($course->format) ? get_string('sectionname', 'format_' . $course->format) : '',
            'column_title_activity_name' => get_string('name'),

            'adleradaptivities' => array_map(function($adleradaptivity) use ($course) {
                return [
                    'section' => course_format_uses_sections($course->format) ? format_string(get_section_name($course, $adleradaptivity->section)) : '',
                    'coursemodule_name' => format_string($adleradaptivity->name),
                    'coursemodule_url' => new moodle_url('/mod/adleradaptivity/view.php', ['id' => $adleradaptivity->coursemodule]),
                ];
            }, $adleradaptivities)
        ];

        // Render the Mustache template with the data.
        return $this->render_from_template('mod_adleradaptivity/index_page', $data);
    }
}