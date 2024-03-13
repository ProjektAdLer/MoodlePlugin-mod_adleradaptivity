<?php

namespace mod_adleradaptivity\local\db;

class moodle_core_repository extends base_repository {
    public function get_course_by_course_id(int $course_id) {
        return $this->db->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
    }
}