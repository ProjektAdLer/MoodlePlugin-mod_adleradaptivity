<?php

// required file per moodle doc https://docs.moodle.org/dev/Activity_modules#index.php
// "  index.php: a page to list all instances in a course"
// One way to reach this view: add sidebar block "Activities" then click on the adler activities link.
// <domain>/mod/adleradaptivity/index.php?id=<course_id>

use mod_adleradaptivity\event\course_module_instance_list_viewed;

require_once('../../config.php');

// The `id` parameter is the course id.
$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/adleradaptivity/index.php', ['id' => $id]);
$course = get_course($id);
//$course = $DB->get_record('course', ['id'=> $id], '*', MUST_EXIST);
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = [
    'context' => $coursecontext
];
$event = course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$PAGE->navbar->add(get_string('modulenameplural', 'adleradaptivity'));
$PAGE->set_title(get_string('modulenameplural', 'adleradaptivity'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'adleradaptivity'), 2);

// Get all the appropriate data.
if (!$adleradaptivitys = get_all_instances_in_course('adleradaptivity', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'adleradaptivity')), "../../course/view.php?id=$course->id");
    die;
}

// Configure table for displaying the list of instances.
$headings = [];
$align = [];

if (course_format_uses_sections($course->format)) {
    $headings[] = get_string('sectionname', 'format_'.$course->format);
    $align[] = 'left';
}

$headings[] = get_string('name');
$align[] = 'left';

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
foreach ($adleradaptivitys as $adleradaptivity) {
    $row = new html_table_row();

    if (course_format_uses_sections($course->format)) {
        $row->cells[] = get_section_name($course, $adleradaptivity->section);
    }

    $row->cells[] = html_writer::link(
        new moodle_url('/mod/adleradaptivity/view.php', ['id' => $adleradaptivity->coursemodule]),
        format_string($adleradaptivity->name)
    );

    $table->data[] = $row;
}

// Display the table.
echo html_writer::table($table);

// Print the footer.
echo $OUTPUT->footer();
