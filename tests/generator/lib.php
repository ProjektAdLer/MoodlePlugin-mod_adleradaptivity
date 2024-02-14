<?php

global $CFG;

class mod_adleradaptivity_generator extends testing_module_generator {
    public function create_instance($record = null, array $options = null) {
        $default_params = [
            'name' => 'name',
            'intro' => 'intro',
            'adaptivity_element_intro' => 'adaptivity_element_intro',
        ];
        $record = array_merge($default_params, $record);

        return parent::create_instance($record, (array)$options);
    }

    public function create_mod_adleradaptivity_task(int $adleradaptivity_id, $params = array(), bool $insert = true) {
        global $DB;
        $default_params = [
            'adleradaptivity_id' => $adleradaptivity_id,
            'title' => 'title',
            'uuid' => 'uuid',
            'required_difficulty' => 100,
        ];
        $params = array_merge($default_params, $params);
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_tasks', $new_object);
        }
        return $new_object;
    }

    public function create_mod_adleradaptivity_question(int $adleradaptivity_task_id, $params = array(), bool $insert = true) {
        global $DB;
        $default_params = [
            'adleradaptivity_task_id' => $adleradaptivity_task_id,
            'difficulty' => 100,
        ];
        $params = array_merge($default_params, $params);
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_questions', $new_object);
        }
        return $new_object;
    }

    public function create_mod_adleradaptivity_attempt(int $attempt_id, int $user_id, bool $insert = true) {
        global $DB;
        $default_params = [
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
        ];
//        $params = array_merge($default_params, $params);
        $params = $default_params;
        $new_object = (object)$params;

        if ($insert) {
            $new_object->id = $DB->insert_record('adleradaptivity_attempts', $new_object);
        }
        return $new_object;
    }
}