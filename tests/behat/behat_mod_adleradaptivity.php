<?php

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.


require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../question/tests/behat/behat_question_base.php');

use Behat\Gherkin\Node\TableNode;

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
    }

    /**
     * Get a adleradaptivity instnace by name.
     *
     * @param string $name adleradaptivity name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_adleradaptivity_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('adleradaptivity', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get a quiz cm from the quiz name.
     *
     * @param string $name quiz name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_adleradaptivity_name(string $name): stdClass {
        $quiz = $this->get_adleradaptivity_by_name($name);
        return get_coursemodule_from_instance('adleradaptivity', $quiz->id, $quiz->course);
    }

    /**
     * Create adler tasks on the specified adleradaptivity element.
     *
     * The first row should be column names:
     * | title | required_difficulty |
     * The first one is required. The others are optional.
     *
     * title                unique name of the task.
     * required_difficulty  required difficulty for the task.
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
            $adleradaptivity_generator->create_mod_adleradaptivity_task($adleradaptivity_cm->instance, $taskdata);
        }

        echo json_encode($data);
    }

    /**
     * Create adler questions for the specified adleradaptivity task.
     *
     * The first row should be column names:
     * | task_title | question_category | question_name | difficulty | singlechoice |
     * The first three are required. The others are optional.
     *
     * task              the name of the task to add the question to.
     * question_category the category of the question.
     * question_name     the name of the question.
     * difficulty        the difficulty of the question.
     * singlechoice      whether the question is single choice or multiple choice.
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
                $questiondata['uuid'] ?? $questiondata['question_name']
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
