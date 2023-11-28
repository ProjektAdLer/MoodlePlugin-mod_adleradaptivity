<?php

namespace local_adler\lib;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/local/adler/tests/lib/static_mock_framework.php');
require_once($CFG->dirroot . '/local/adler/vendor/autoload.php');

use advanced_testcase;
use externallib_advanced_testcase;
use Mockery;

trait general_testcase_adjustments{
    public function setUp(): void {
        parent::setUp();

        // set default value: reset DB after each test case
        $this->resetAfterTest();

        // if creating multiple mocks of the same class (in my example context_module) in different tests or
        // same test with different parameters Mockery always reused the first mock created for that class.
        // This is not desired, because test cases should be independent of each other. Therefore, the
        // Mockery container is reset after each test case.
        Mockery::resetContainer();
    }

    public function tearDown(): void {
        parent::tearDown();

        // Moodle thinks debugging messages should be tested (check for debugging messages in unit tests).
        // Imho this is very bad practice, because devs should be encouraged to provide additional Information
        // for debugging. Checking for log messages in tests provides huge additional effort (e.g. tests will fail because
        // a message was changed / an additional one was added / ...). Because logging should NEVER affect the
        // functionality of the code, this is completely unnecessary. Where this leads can be perfectly seen in all
        // moodle code: Things work or do not work and there is no feedback on that. Often things return null if successfully
        // and if nothing happened (also categorized as successful), but no feedback is available which of both cases happened.
        // Users and devs very often can't know why something does not work.
        // If something went wrong either the code should handle the problem or it should throw an exception.
        $this->resetDebugging();
    }
}

abstract class adler_testcase extends advanced_testcase {
    use general_testcase_adjustments;
}

abstract class adler_externallib_testcase extends externallib_advanced_testcase {
    use general_testcase_adjustments {
        general_testcase_adjustments::setUp as protected setUpTrait;
    }

    public function setUp(): void {
        $this->setUpTrait();

        // As of moodle 4.2 it is not possible anymore to load this file outside a test case. This file is now deprecated and
        // only contains aliases to the new implementation. Moodle devs want to be sure this file is only imported in tests
        // relying on the deprecated functions. Therefore, this file is not allowed anymore to be required in a way that
        // other testcases (in other test files) have the file loaded (1). The file can only be loaded inside an isolated testcase.
        // Because this plugin has to be compatible with moodle version 3.11 it has to use the legacy functions (as stated in the
        // Moodle 4.2 developer update) (2).
        //
        // 1) https://github.com/moodle/moodle/commit/1a53cbbae4b3ceeb17177b02203eaa6abff75a52
        // 2) https://github.com/moodle/devdocs/blob/1a1d15ddd75688ca82babb6fb84b9ddd641fbcef/docs/devupdate.md
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');
    }
}