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

#    ANF-ID: [MVP15, MVP17, MVP16, MVP14]
  Scenario: Display module without any attempts
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".module-failure" element
    And I should see "1" ".task-not-attempted" element
    And I should see "1" ".task-optional-not-attempted" element
    And I should not see ".question-status-success"

#    ANF-ID: [MVP15, MVP17, MVP16, MVP14]
  Scenario: Display attempt not sufficient to complete the module
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer    |
      | Q1            | correct   |
      | Q2            | incorrect |
      | Q4            | incorrect |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".module-failure" element
    And I should see "1" ".question-status-success" elements
    And I should see "1" ".task-incorrect" element
    And I should see "1" ".task-optional-incorrect" element

#    ANF-ID: [MVP15, MVP17, MVP16, MVP14]
  Scenario: Display attempt sufficient to complete the module
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer  |
      | Q2            | correct |
      | Q4            | correct |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".module-success" element
    And I should see "2" ".question-status-success" elements
    And I should not see a ".task-optional-incorrect" element
    And I should not see a ".task-optional-not-attempted" element
    And I should see a ".task-correct" element

#    ANF-ID: [MVP15, MVP17, MVP16, MVP14]
  Scenario: Display module with a question that has multiple references to it (in another module)
    Given the following "activities" exist:
      | activity        | name             | intro                  | course | completion |
      | adleradaptivity | Adler Activity 2 | Adler Activity 2 Intro | C1     | 2          |
    And adleradaptivity "Adler Activity 2" contains the following tasks:
      | title   | required_difficulty |
      | Task2_1 | 100                 |
    And the following adleradaptivity questions are added:
      | task_title | question_category | question_name | difficulty |
      | Task2_1    | Test questions    | Q1            | 0          |
    When I am on the "Adler Activity 2" "mod_adleradaptivity > View" page logged in as "student"
    Then I should see a ".module-failure" element
    And I should see "1" ".task-not-attempted" element
    And I should not see ".question-status-success"
