<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use mod_adleradaptivity\external\answer_questions;
use mod_adleradaptivity\lib\adler_testcase;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


class backup_mod_adleradaptivity_test extends adler_testcase {
    private ?array $course_data = null;
    private ?backup_controller $bc = null;
    private $plugin_generator;

    public function setUp(): void {
        parent::setUp();
        $this->plugin_generator = $this->getDataGenerator()->get_plugin_generator('mod_adleradaptivity');

        $task_required = true;
        $singlechoice = false;
        $q2 = false;
        $q1 = 'correct';

        // cheap way of creating test data
        // create course with test questions and user
        $this->course_data = $this->plugin_generator->create_course_with_test_questions($this->getDataGenerator(), $task_required, $singlechoice, $q2 != 'none');

        // sign in as user
        $this->setUser($this->course_data['user']);

        // generate answer data
        $answerdata = $this->plugin_generator->generate_answer_question_parameters($q1, $q2, $this->course_data);

        $answer_question_result = answer_questions::execute($answerdata[0], $answerdata[1]);

        // data creation finish

        // Create a backup of the module.
        $this->bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->course_data['module']->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            2
        );
        $this->bc->execute_plan();
        $this->bc->destroy();
    }

    /**
     * # ANF-ID: [MVP1]
     */
    public function test_backup() {
        // Get xml from backup.
        $module_xml = $this->get_xml_from_backup($this->bc);
        $adleradaptivity_xml = $this->get_xml_from_backup($this->bc, 'adleradaptivity');
        $completion_xml = $this->get_xml_from_backup($this->bc, 'completion');

        // validate xml values
        $this->assertEquals('adleradaptivity', $module_xml->modulename);

        $this->assertArrayHasKey('name', (array)$adleradaptivity_xml->adleradaptivity);
        $this->assertArrayHasKey('tasks', (array)$adleradaptivity_xml->adleradaptivity);
        $this->assertCount('2', $adleradaptivity_xml->adleradaptivity->tasks->task);
        $this->assertCount('1', $adleradaptivity_xml->adleradaptivity->attempts->attempt);
        // check attempts
        $this->assertArrayHasKey('attempts', (array)$adleradaptivity_xml->adleradaptivity);
        $this->assertCount('1', $adleradaptivity_xml->adleradaptivity->attempts->attempt);
        $this->assertArrayHasKey('question_usage', (array)$adleradaptivity_xml->adleradaptivity->attempts->attempt[0]);


        // check completion xml
        $this->assertCount('1', $completion_xml->completion->completionstate);
    }

    /**
     * # ANF-ID: [MVP2]
     */
    public function test_restore() {
        global $DB;

        $version_with_mod_qbank = $DB->record_exists('modules', ['name' => 'qbank']);

        // delete course
        delete_course($this->course_data['course']->id);
        $this->assertCount(0, $DB->get_records('adleradaptivity_questions'));
        $this->assertCount(
            $version_with_mod_qbank ? 1 : 0,  // https://tracker.moodle.org/browse/MDL-71378
            $DB->get_records('course_modules'));

        // create user with restore capabilities
        $admin_user = $this->getDataGenerator()->create_user();
        // assign role admin
        $context = context_system::instance();
        role_assign(1, $admin_user->id, $context);


        // restore course
        $courseid = restore_dbops::create_new_course('', '', $this->course_data['course']->category);

        $backup_file_path = $this->bc->get_results()['backup_destination']->copy_content_to_temp();
        $foldername = restore_controller::get_tempdir_name($courseid, $this->course_data['user']->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $tempdir = make_backup_temp_directory($foldername);
        $files = $fp->extract_to_pathname($backup_file_path, $tempdir);

        $rc = new restore_controller(
            $foldername,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $admin_user->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // get restored course
        $restored_course = get_course($courseid);
        // get modules of $restored_course
        $restored_modules = get_course_mods($restored_course->id);
        // get first element
        $restored_module = reset($restored_modules);
        // get restored questions from question engine
        $restored_adler_questions = $DB->get_records('adleradaptivity_questions');

        // verify restored course
        $this->assertCount(
            $version_with_mod_qbank ? 2 : 1,   // https://tracker.moodle.org/browse/MDL-71378
            $DB->get_records('course_modules'));
        $this->assertEquals('adleradaptivity', $restored_module->modname);
        $this->assertCount(2, $restored_adler_questions);

        // now verify attempt restore

        // just loading the attempt objects from db already confirms that the attempt was restored
        // get restored question_usage
        $restored_module_context_id = context_module::instance($restored_module->id)->id;
        $restored_question_usages = $DB->get_records('question_usages', ['contextid' => $restored_module_context_id]);
        $restored_question_usages = reset($restored_question_usages);
        // get adleradaptivity attempt object
        $restored_adler_attempt = $DB->get_record('adleradaptivity_attempts', ['attempt_id' => $restored_question_usages->id]);

        // just check that the object is not empty
        $this->assertArrayHasKey('attempt_id', (array)$restored_adler_attempt);
        $this->assertEquals($restored_module->instance, $restored_adler_attempt->adleradaptivity_id);
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