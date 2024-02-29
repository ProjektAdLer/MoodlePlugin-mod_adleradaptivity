@mod @mod_adleradaptivity
Feature: View an adleradaptivity
  As a student
  In order to see my progress in the adaptive module
  I need the module to show the current progress of the module, tasks and questions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity        | name             | intro                  | course | completion |
      | adleradaptivity | Adler Activity 1 | Adler Activity 1 Intro | C1     | 2          |
    And adleradaptivity "Adler Activity 1" contains the following tasks:
      | title | required_difficulty |
      | Task1 | 100                 |
      | Task2 | null                |
    And the following adleradaptivity questions are added:
      | task_title | question_category | question_name | difficulty |
      | Task1      | Test questions    | Q1            | 0          |
      | Task1      | Test questions    | Q2            | 100        |
      | Task1      | Test questions    | Q3            | 200        |
      | Task2      | Test questions    | Q4            | 0          |

  Scenario: Display module without any attempts
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".behat_module-failure" element
    # todo: implement the class stuff for tasks (get_task_status_message_translation_key)
    And I should see "1" ".behat_task-not-attempted" element
    And I should see "1" ".behat_task-optional-not-attempted" element
    And I should not see ".behat_question-status-success"

  Scenario: Display attempt not sufficient to complete the module
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer    |
      | Q1            | correct   |
      | Q2            | incorrect |
      | Q4            | incorrect |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".behat_module-failure" element
    And I should see "1" ".behat_question-status-success" elements
    And I should see "1" ".behat_task-incorrect" element
    And I should see "1" ".behat_task-optional-incorrect" element

  Scenario: Display attempt sufficient to complete the module
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer  |
      | Q2            | correct |
      | Q4            | correct |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".behat_module-success" element
    And I should see "2" ".behat_question-status-success" elements
    And I should not see a ".behat_task-optional-incorrect" element
    And I should not see a ".behat_task-optional-not-attempted" element
    And I should see a ".behat_task-correct" element
