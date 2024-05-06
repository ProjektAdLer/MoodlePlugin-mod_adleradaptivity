<?php

// unused, just for reference. Don't commit

namespace mod_adleradaptivity\event;

use core\event\base as core_event_base;

class question_answered extends core_event_base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('event_question_answered', 'mod_adleradaptivity');
    }
}