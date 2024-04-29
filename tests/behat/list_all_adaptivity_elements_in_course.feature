@mod @mod_adleradaptivity
Feature: View adleradaptivity index page
  As a student
  In order to see all adleradaptivity activities
  I need to be able to view the index page

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

  Scenario: View the index page with two adleradaptivity activities
    Given the following "activities" exist:
      | activity        | name             | intro                  | course | completion |
      | adleradaptivity | Adler Activity 1 | Adler Activity 1 Intro | C1     | 2          |
      | adleradaptivity | Adler Activity 2 | Adler Activity 2 Intro | C1     | 2          |
    When I am on the "Course 1" "mod_adleradaptivity > Index" page logged in as "student"
    Then I should see "Adler Activity 1"
    And I should see "Adler Activity 2"
    And I should see "Course 1"
    And I should see "2" ".generaltable tbody tr" elements

  Scenario: Check if the links for each activity correctly redirect to the respective activity page; test 1
    Given the following "activities" exist:
      | activity        | name             | intro                  | course | completion |
      | adleradaptivity | Adler Activity 1 | Adler Activity 1 Intro | C1     | 2          |
      | adleradaptivity | Adler Activity 2 | Adler Activity 2 Intro | C1     | 2          |
    When I am on the "Course 1" "mod_adleradaptivity > Index" page logged in as "student"
    And I click on "Adler Activity 1" "link" in the ".generaltable" "css_element"
    Then the url should match "mod/adleradaptivity/view.php"
    And I should see "Adler Activity 1"
    And I should see "0" ".errormessage" elements

  Scenario: Check if the links for each activity correctly redirect to the respective activity page; test 2
    Given the following "activities" exist:
      | activity        | name             | intro                  | course | completion |
      | adleradaptivity | Adler Activity 1 | Adler Activity 1 Intro | C1     | 2          |
      | adleradaptivity | Adler Activity 2 | Adler Activity 2 Intro | C1     | 2          |
    When I am on the "Course 1" "mod_adleradaptivity > Index" page logged in as "student"
    And I click on "Adler Activity 2" "link" in the ".generaltable" "css_element"
    Then the url should match "mod/adleradaptivity/view.php"
    And I should see "Adler Activity 2"
    And I should see "0" ".errormessage" elements

  Scenario: Scenario: Check if the page displays correctly when there are no mod_adleradaptivity activities in the course
    When I am on the "Course 1" "mod_adleradaptivity > Index" page logged in as "student"
    Then I should not see ".generaltable"
    And the url should match "mod/adleradaptivity/index.php"
