@mod @mod_adleradaptivity
Feature: Attempt an adleradaptivity
  As a student
  In order to check my knowledge and get recommendations
  I need to be able to attempt adleradaptivity

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
#    TODO: do it like the_following_entities_exist
    And the following adleradaptivity questions are added:
      | task_title | question_category | question_name | difficulty | questiontext |
      | Task1      | Test questions    | Q1            | 0          | Q1           |
      | Task1      | Test questions    | Q2            | 100        | Q2           |
      | Task1      | Test questions    | Q3            | 200        | Q3           |

  @javascript
  Scenario: Attempt a question with an correct answer
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
#    Question is: which numbers are odd?
    And I click on "One" "checkbox" in the "Q2" "question"
    And I click on "Three" "checkbox" in the "Q2" "question"
    And I click on "Check" "button" in the "Q2" "question"

#    And I submit question "Q2" with a "correct" answer
    Then I should see a ".behat_module-success" element
    And I should see a ".behat_task-correct" element


  Scenario: Attempt a question with an incorrect answer
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    And I submit question "Q2" with an "incorrect" answer
    Then I should see a ".behat_module-failure" element
    And I should see a ".behat_task-incorrect" element

  Scenario: Attempt a question with a wrong answer that was previously correct and completed the task
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer  |
      | Q2            | correct |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    And I submit question "Q2" with an "incorrect" answer
    Then I should see a ".behat_module-success" element
    And I should see a ".behat_task-correct" element
#    TODO: check that the currently shown answer is marked wrong

