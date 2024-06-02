<?php
global $USER;

/**
 * Prints an instance of mod_adleradaptivity.
 *
 * @package     mod_adleradaptivity
 * @copyright   2023 Markus Heck
 */

use mod_adleradaptivity\local\output\pages\view_page;

require(__DIR__.'/../../config.php');


// moodle component library
// - https://moodledev.io/general/development/tools/component-library
// - https://componentlibrary.moodle.com/admin/tool/componentlibrary/docspage.php/moodle/components/moodle-icons/

// TODO: attempts report (likely h5p)
// TODO permission check (only own latest attempt, except for admin, also in processattempt)

new view_page();
