@local_saipa @local_saipa_chat
Feature: SAIPA chat widget for students
  As a student enrolled in a course
  I need to be able to open the SAIPA chat block
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
      | saipa      | Course       | SAIPATEST | course-view-*   | side-post     |

  @javascript
  Scenario: Student sees SAIPA chat block when enrolled
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should see "SAIPA" in the "side-post" "region"

  @javascript
  Scenario: Student can open the chat interface
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    When I click on "Abrir chat SAIPA" "link" in the "side-post" "region"
    Then I should see the SAIPA chat input field

  @javascript
  Scenario: Teacher sees SAIPA dashboard link in the block
    Given I am on the "SAIPATEST" "course" page logged in as "teacher1"
    Then I should see "Dashboard SAIPA" in the "side-post" "region"

  @javascript
  Scenario: Unenrolled user does not see SAIPA block
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | outsider1 | Pedro     | Foraneo  | outsider@example.com  |
    And I am on the "SAIPATEST" "course" page logged in as "outsider1"
    Then I should not see "SAIPA" in the "side-post" "region"
