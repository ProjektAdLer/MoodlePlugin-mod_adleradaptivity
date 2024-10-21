<?php
global $USER;

/**
 * Prints an instance of mod_adleradaptivity.
 *
 * @package     mod_adleradaptivity
 * @copyright   2023 Markus Heck
 */

use core\di;
use mod_adleradaptivity\local\output\pages\view_page;

require(__DIR__.'/../../config.php');


// moodle component library
// - https://moodledev.io/general/development/tools/component-library
// - https://componentlibrary.moodle.com/admin/tool/componentlibrary/docspage.php/moodle/components/moodle-icons/

di::get(view_page::class);