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
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
#    And the following "questions" exist:
#      | questioncategory | qtype | template          | name | questiontext   |
#      | Test questions   | multichoice | one_of_four |TF1  | First question |
    And the following "activities" exist:
      | activity        | name             | intro                  | course |
      | adleradaptivity | Adler Activity 1 | Adler Activity 1 Intro | C1     |
#      | activity | name   | intro              | course | idnumber | grade | navmethod  |
#      | adleradaptivity     | Adleradaptivity 1 | Adleradaptivity 1 description | C1     | dleradaptivity1    | 100   | free       |
#      | quiz     | Quiz 2 | Quiz 2 description | C1     | quiz2    | 6     | free       |
#      | quiz     | Quiz 3 | Quiz 3 description | C1     | quiz3    | 100   | free       |
#      | quiz     | Quiz 4 | Quiz 4 description | C1     | quiz4    | 100   | sequential |
    And adleradaptivity "Adler Activity 1" contains the following tasks:
      | title | required_difficulty |
      | Task1 | 0                   |
      | Task2 | 100                 |
    And adleradaptivity_task "Task1" contains the following alderadaptivity_questions:
      | questioncategory | name | Task  | difficulty |
      | Test questions   | Q1   | Task1 | 0          |
      | Test questions   | Q2   | Task1 | 100        |
      | Test questions   | Q3   | Task1 | 200        |
      | Test questions   | Q3   | Task2 | 0          |





#  @javascript
  Scenario: Attempt a quiz with a single unnamed section, review and re-attempt
    Given user "student" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I follow "Review"
    Then I should see "Started on"
    And I should see "State"
    And I should see "Completed on"
    And I should see "Time taken"
    And I should see "Marks"
    And I should see "Grade"
    And I should see "25.00 out of 100.00"
    And I follow "Finish review"
    And I press "Re-attempt quiz"



