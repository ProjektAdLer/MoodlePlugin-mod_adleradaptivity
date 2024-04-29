<?php

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.


require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../question/tests/behat/behat_question_base.php');
require_once(__DIR__ . '/../external/external_test_helpers.php');  // TODO: this should be autoloaded. maybe it does not because the tests folder does not follow default auload folder structure


use Behat\Gherkin\Node\TableNode;
use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\external\external_test_helpers;
use mod_adleradaptivity\local\helpers;

class behat_mod_adleradaptivity extends behat_question_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
//        TODO
//        switch (strtolower($page)) {
//            default:
//                throw new Exception('Unrecognised quiz page type "' . $page . '."');
//        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                                | description                                  |
     *
     * @param string $type identifies which type of page this is, e.g. 'Attempt review'.
     * @param string $identifier identifies the particular page, e.g. 'Test quiz > student > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url(
                    '/mod/adleradaptivity/view.php',
                    ['id' => $this->get_cm_by_adleradaptivity_name($identifier)->id]
                );
            case 'index':
                return new moodle_url('/mod/adleradaptivity/index.php',
                    ['id' => $this->get_course_by_course_name($identifier)->id]
                );
            default:
                throw new Exception('Unrecognised quiz page type "' . $type . '."');
        }
    }

    protected function get_course_by_course_name(string $course_name)  {
        global $DB;
        return $DB->get_record('course', ['fullname' => $course_name], '*', MUST_EXIST);
    }

    /**
     * Get an adleradaptivity instance by name.
     *
     * @param string $name adleradaptivity name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_adleradaptivity_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('adleradaptivity', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get an adleradaptivity cm from the adleradaptivity name.
     *
     * @param string $name quiz name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_adleradaptivity_name(string $name): stdClass {
        $quiz = $this->get_adleradaptivity_by_name($name);
        return get_coursemodule_from_instance('adleradaptivity', $quiz->id, $quiz->course);
    }

    /**
     * Checks, that current page PATH matches regular expression
     * Example: Then the url should match "superman is dead"
     * Example: Then the uri should match "log in"
     * Example: And the url should match "log in"
     *
     * Taken from MinkExtension
     *
     * @Then /^the (?i)url(?-i) should match (?P<pattern>"(?:[^"]|\\")*")$/
     */
    public function assertUrlRegExp($pattern)
    {
        $this->assertSession()->addressMatches($this->fixStepArgument($pattern));
    }

    /**
     * Returns fixed step argument (with \\" replaced back to ")
     *
     * Taken from MinkExtension
     *
     * @param string $argument
     *
     * @return string
     */
    protected function fixStepArgument($argument)
    {
        return str_replace('\\"', '"', $argument);
    }

    /**
     * Checks, that element with specified CSS exists on page
     * Example: Then I should see a "body" element
     * Example: And I should see a "body" element
     *
     * Taken from MinkExtension
     *
     * @Then /^(?:|I )should see an? "(?P<element>[^"]*)" element$/
     */
    public function assertElementOnPage($element) {
        $this->assertSession()->elementExists('css', $element);
    }

    /**
     * Checks, that element with specified CSS does not exist on page
     * Example: Then I should not see a "body" element
     *
     * Taken from MinkExtension
     *
     * @Then /^(?:|I )should not see an? "(?P<element>[^"]*)" element$/
     */
    public function assertElementNotOnPage($element) {
        $this->assertSession()->elementNotExists('css', $element);
    }

    /**
     * Checks, that element with specified CSS exists exactly the specified number of times on page
     * Example: Then I should see 3 "div" elements
     *
     * @Then /^I should see "(?P<num>\d+)" "(?P<element>[^"]*)" elements?$/
     */
    public function assertNumElementsOnPage($num, $element) {
        $this->assertSession()->elementsCount('css', $element, $num);
    }

    /**
     * Create question usages (attempts) for the specified adleradaptivity and user.
     * The first row has to be column names:
     * | question_name | answer |
     * The first two are required.
     *
     * question_name the unique name of the question.
     * answer       whether the question was answered correctly. Allowed values are 'correct', 'incorrect', 'all_true', 'all_false' and 'partially_correct'.
     *
     * Then the following rows should be the data for the question usages.
     *
     * @param string $adleradaptivityname the name of the adleradaptivity to add question usages to.
     * @param string $username the username of the user to add question usages for.
     * @param TableNode $data information about the question usages to add.
     *
     * @Given /^user "([^"]*)" has attempted "([^"]*)" with results:$/
     */
    public function user_has_attempted_with_results(string $username, string $adleradaptivityname, TableNode $data) {
// TODO refactor methods from "main" code as they are (now) also used in test code
        global $DB;

        // load adleradaptivity cm
        $adleradaptivity_id = $DB->get_field('adleradaptivity', 'id', ['name' => $adleradaptivityname], MUST_EXIST);
        $cmid = $DB->get_field('course_modules', 'id', ['instance' => $adleradaptivity_id, 'module' => $DB->get_field('modules', 'id', ['name' => 'adleradaptivity'])], MUST_EXIST);
        $module = get_coursemodule_from_id('adleradaptivity', $cmid, 0, false, MUST_EXIST);

        // get current user
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        // login as $user. This is required for completion as I cannot pass a user id in the custom completion rule
        advanced_testcase::setUser($user->id);

        // generate moodle and adleradaptivity attempt
        $quba = helpers::load_or_create_question_usage($cmid);

        // answer questions
        foreach ($data->getHash() as $questiondata) {
            // get question_definition object
            $question_id = $DB->get_field('question', 'id', ['name' => $questiondata['question_name']], MUST_EXIST);
            $question_definition = question_bank::load_question($question_id);
            // generate answer
            $answer = json_encode(external_test_helpers::gernerate_question_answers_for_single_question($questiondata['answer'], $question_definition));

            // answer question
            answer_questions::process_single_question(
                [
                    'uuid' => $question_definition->idnumber,
                    'answer' => $answer,
                ],
                time(),
                $quba);
        }

        // save current questions usage
        question_engine::save_questions_usage_by_activity($quba);
        // Update completion state
        answer_questions::update_module_completion($module);
    }

    /**
     * Create adler tasks on the specified adleradaptivity element.
     *
     * The first row has to be column names:
     * | title | required_difficulty |
     * The first one is required. The others are optional.
     *
     * title                unique name of the task.
     * required_difficulty  required difficulty for the task. Empty string or null for optional tasks.
     *
     * Then the following rows should be the data for the tasks.
     *
     * @param string $adleradaptivityname the name of the adleradaptivity to add tasks to.
     * @param TableNode $data information about the tasks to add.
     *
     * @Given /^adleradaptivity "([^"]*)" contains the following tasks:$/
     */
    public function adleradaptivity_contains_the_following_tasks(string $adleradaptivityname, TableNode $data) {
        global $DB;

        $adleradaptivity_cm = $this->get_cm_by_adleradaptivity_name($adleradaptivityname);


        $adleradaptivity_generator = behat_util::get_data_generator()->get_plugin_generator('mod_adleradaptivity');

        foreach ($data->getHash() as $taskdata) {
            $taskdata['uuid'] = $taskdata['title'];
            $taskdata['required_difficulty'] = strlen($taskdata['required_difficulty']) == 0 || $taskdata['required_difficulty'] == 'null' ? null : $taskdata['required_difficulty'];
            $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_cm->instance, $taskdata);
        }

        echo json_encode($data);
    }

    /**
     * Create adler questions for the specified adleradaptivity task.
     *
     * The first row has to be column names:
     * | task_title | question_category | question_name | difficulty | singlechoice | questiontext |
     * The first three are required. The others are optional.
     *
     * task              the name of the task to add the question to.
     * question_category the category of the question.
     * question_name     the name of the question. This value is also used as questiontext as it is the value used to reference questions via other behat steps. Additionally, it is used as the uuid for the question.
     * difficulty        the difficulty of the question.
     * singlechoice      whether the question is single choice or multiple choice.
     * questiontext      the text of the question.
     *
     * Then the following rows should be the data for the questions.
     *
     * @param TableNode $data information about the questions to add.
     *
     * @Given /^the following adleradaptivity questions are added:$/
     */
    public function the_following_adleradaptivity_questions_are_added(TableNode $data) {
        global $DB;

        $adleradaptivity_generator = behat_util::get_data_generator()->get_plugin_generator('mod_adleradaptivity');

        foreach ($data->getHash() as $questiondata) {
            // get existing task from $data['task_title']
            $task = $DB->get_record_sql(
                "SELECT * FROM {adleradaptivity_tasks} WHERE " . $DB->sql_compare_text('title') . " = ?",
                [$questiondata['task_title']]
            );
            // get existing adleradaptivity cm from $task
            $adleradaptivity_cm = get_coursemodule_from_instance('adleradaptivity', $task->adleradaptivity_id);
            // get existing qcat from $data['question_category']
            $qcat = $DB->get_record('question_categories', ['name' => $questiondata['question_category']], '*', MUST_EXIST);

            // create adleradaptivity question
            $adleradaptivity_question = $adleradaptivity_generator->create_mod_adleradaptivity_question($task->id, [
                'difficulty' => $questiondata['difficulty'] ?? 100,
            ]);

            // create moodle question
            $question = $adleradaptivity_generator->create_moodle_question(
                $qcat->id,
                $questiondata['singlechoice'] ?? false,
                $questiondata['question_name'],
                $questiondata['uuid'] ?? $questiondata['question_name'],
                $questiondata['question_name'] ?? null
            );

            // create question reference
            $adleradaptivity_generator->create_question_reference(
                $adleradaptivity_question->id,
                $question->questionbankentryid,
                $adleradaptivity_cm->id
            );
        }
        echo "Created question: ";
    }

//    ATM not required by this plugin
//    /**
//     * Return a list of the exact named selectors for the component.
//     *
//     * @return behat_component_named_selector[]
//     */
//    public static function get_exact_named_selectors(): array {
//        return [];
//    }
}
