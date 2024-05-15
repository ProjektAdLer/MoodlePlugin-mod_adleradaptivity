<?php

namespace mod_adleradaptivity\local\db;

use dml_exception;
use stdClass;

class adleradaptivity_repository extends base_repository {
    /**
     * @throws dml_exception
     */
    public function create_adleradaptivity(stdClass $module_instance): bool|int {
        return $this->db->insert_record('adleradaptivity', $module_instance);
    }

    /**
     * @throws dml_exception
     */
    public function delete_adleradaptivity_by_id(int $instance_id): bool {
        return $this->db->delete_records('adleradaptivity', ['id' => $instance_id]);
    }

    /**
     * @throws dml_exception
     */
    public function get_instance_by_instance_id(int $instance_id): false|stdClass {
        return $this->db->get_record('adleradaptivity', ['id' => $instance_id], '*', MUST_EXIST);
    }
}