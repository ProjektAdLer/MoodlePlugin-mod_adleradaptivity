<?php

use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\external\external_test_helpers;
use mod_adleradaptivity\lib\adler_testcase;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


/**
 * @runTestsInSeparateProcesses
 */
class backup_mod_adleradaptivity_test extends adler_testcase {
    public function test_backup() {
        $task_required = true;
        $singlechoice = false;
        $q2 = false;
        $q1 = 'correct';

        // cheap way of creating test data
        // create course with test questions and user
        $course_data = external_test_helpers::create_course_with_test_questions($this->getDataGenerator(), $task_required, $singlechoice, $q2 != 'none');

        // sign in as user
        $this->setUser($course_data['user']);

        // generate answer data
        $answerdata = external_test_helpers::generate_answer_question_parameters($q1, $q2, $course_data);

        $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // data creation finish

        // Create a backup of the module.
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $course_data['module']->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            2
        );
        $bc->execute_plan();
        $bc->destroy();

        // Get xml from backup.
        $module_xml = $this->get_xml_from_backup($bc);
        $adleradaptivity_xml = $this->get_xml_from_backup($bc, 'adleradaptivity');
        $completion_xml = $this->get_xml_from_backup($bc, 'completion');

        // validate xml values
        $this->assertEquals('adleradaptivity', $module_xml->modulename);

        $this->assertArrayHasKey('name', (array)$adleradaptivity_xml->adleradaptivity);
        $this->assertArrayHasKey('tasks', (array)$adleradaptivity_xml->adleradaptivity);
        $this->assertEquals('2', count($adleradaptivity_xml->adleradaptivity->tasks->task));
        $this->assertEquals('1', count($adleradaptivity_xml->adleradaptivity->attempts->attempt));

        $this->assertEquals('1', count($completion_xml->completion->completionstate));

    }

    /** Get parsed xml from backup controller object.
     * @param $bc backup_controller
     * @param $type string type of backup, one of 'module', 'course'
     * @return false|SimpleXMLElement
     */
    private function get_xml_from_backup(backup_controller $bc, string $type = 'module') {
        // Get the backup file.
        $file = $bc->get_results();
        $file = reset($file);

        // Extract file to temp dir.
        $tempdir = make_request_directory();
        $extracted_files = $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $tempdir);

        // Search for entry of <type>.xml file and get the full path.
        $type_xml = null;
        foreach ($extracted_files as $key => $_) {
            if (strpos($key, $type . '.xml') !== false) {
                $type_xml = $key;
                break;
            }
        }
        $module_xml_path = $tempdir . DIRECTORY_SEPARATOR . $type_xml;

        // Get the backup file contents and parse it.
        $contents = file_get_contents($module_xml_path);
        return simplexml_load_string($contents);
    }
}