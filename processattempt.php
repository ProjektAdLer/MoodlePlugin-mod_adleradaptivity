<?php

use core\di;
use mod_adleradaptivity\local\output\pages\processattempt_page;

require_once(__DIR__ . '/../../config.php');

# reference: quiz\processattempt.php

di::get(processattempt_page::class);
