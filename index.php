<?php

// required file per moodle doc https://docs.moodle.org/dev/Activity_modules#index.php
// "  index.php: a page to list all instances in a course"
// One way to reach this view: add sidebar block "Activities" then click on the adler activities link.
// <domain>/mod/adleradaptivity/index.php?id=<course_id>

use mod_adleradaptivity\local\output\pages\index_page;

require_once('../../config.php');

new index_page();
