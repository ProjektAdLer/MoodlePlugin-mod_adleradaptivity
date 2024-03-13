<?php

namespace mod_adleradaptivity\local\db;

class adleradaptivity_repository extends base_repository {
    public function get_instance_by_instance_id(int $instance_id) {
        return $this->db->get_record('adleradaptivity', ['id' => $instance_id], '*', MUST_EXIST);
    }
}