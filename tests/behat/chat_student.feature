@local @local_saipa @local_saipa_chat
Feature: SAIPA chat widget for students
  As a student enrolled in a course
  I need to be able to use the SAIPA chat widget
  So that I can ask questions about the course content

  Background:
    Given the following "courses" exist:
      | fullname       | shortname | category |
      | SAIPA Test     | SAIPATEST | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                    |
      | student1 | Carlos    | Alumno   | student1@example.com     |
      | teacher1 | Ana       | Docente  | teacher1@example.com     |
    And the following "course enrolments" exist:
      | user     | course    | role           |
      | student1 | SAIPATEST | student        |
      | teacher1 | SAIPATEST | editingteacher |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | saipa      | Course       | SAIPATEST | course-view-*   | side-pre      |

  @javascript
  Scenario: Student sees SAIPA chat block when enrolled
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    Then "SAIPA Assistant" "block" should exist

  @javascript
  Scenario: Student can type into the chat input field
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should see the SAIPA chat input field
    When I type "How do I submit the assignment?" into the SAIPA chat input field
    Then the SAIPA chat input field should contain "How do I submit the assignment?"

  @javascript
  Scenario: Teacher sees SAIPA dashboard link in the block
    Given I am on the "SAIPATEST" "course" page logged in as "teacher1"
    Then I should see "Student panel" in the "SAIPA Assistant" "block"

  @javascript
  Scenario: Student does not see the teacher dashboard link
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should not see "Student panel" in the "SAIPA Assistant" "block"

  @javascript
  Scenario: Unenrolled user does not see SAIPA block
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | outsider1 | Pedro     | Foraneo  | outsider@example.com  |
    And I am on the "SAIPATEST" "course" page logged in as "outsider1"
    Then "SAIPA Assistant" "block" should not exist
