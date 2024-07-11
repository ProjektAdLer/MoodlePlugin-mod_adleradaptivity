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
    And the following adleradaptivity questions are added:
      | task_title | question_category | question_name | difficulty |
      | Task1      | Test questions    | Q1            | 0          |
      | Task1      | Test questions    | Q2            | 100        |
      | Task1      | Test questions    | Q3            | 200        |

#    ANF-ID: [MVP18, MVP17, MVP16, MVP14]
  @javascript
  Scenario: Attempt a question with an correct answer
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
#    Question is: which numbers are odd?
    And I click on "One" "qtype_multichoice > Answer" in the "Q2" "question"
    And I click on "Three" "qtype_multichoice > Answer" in the "Q2" "question"
    And I click on "Check" "button" in the "Q2" "question"
    Then I should see a ".module-success" element
    And I should see a ".task-correct" element

#    ANF-ID: [MVP18, MVP17, MVP16, MVP14]
  @javascript
  Scenario: Attempt a question with an incorrect answer
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    And I click on "Two" "qtype_multichoice > Answer" in the "Q2" "question"
    And I click on "Check" "button" in the "Q2" "question"
    Then I should see a ".module-failure" element
    And I should see a ".task-incorrect" element

#    ANF-ID: [MVP15, MVP18, MVP17, MVP16, MVP14]
  @javascript
  Scenario: Attempt a question with a wrong answer that was previously correct and completed the task
    Given user "student" has attempted "Adler Activity 1" with results:
      | question_name | answer  |
      | Q2            | correct |
    When I am on the "Adler Activity 1" "mod_adleradaptivity > View" page logged in as "student"
    And I click on "Two" "qtype_multichoice > Answer" in the "Q2" "question"
    And I click on "Check" "button" in the "Q2" "question"
    Then I should see a ".module-success" element
    And I should see a ".task-correct" element
#    question is still considered correct, as it was answered correctly before, but the current answer is wrong
    And I should see a ".question-status-success" element
    And I should see "Partially correct"
