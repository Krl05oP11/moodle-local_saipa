@local_saipa @local_saipa_dashboard
Feature: SAIPA teacher dashboard
  As a teacher enrolled in a course
  I need to access the SAIPA teacher dashboard
  So that I can monitor student risk and engagement

  Background:
    Given the following "courses" exist:
      | fullname   | shortname | category |
      | SAIPA Test | SAIPATEST | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Ana       | Docente  | teacher1@example.com |
      | student1 | Carlos    | Alumno   | student1@example.com |
      | student2 | Luisa     | Alumna   | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course    | role           |
      | teacher1 | SAIPATEST | editingteacher |
      | student1 | SAIPATEST | student        |
      | student2 | SAIPATEST | student        |

  @javascript
  Scenario: Teacher can access the dashboard page
    Given I am on the "SAIPATEST" "course" page logged in as "teacher1"
    When I navigate to "local_saipa > teacher.php" with course "SAIPATEST"
    Then I should see "SAIPA" in the page title
    And I should see "Student Activity"

  @javascript
  Scenario: Teacher sees enrolled students listed in the dashboard
    Given I am on the SAIPA teacher dashboard for "SAIPATEST" logged in as "teacher1"
    Then I should see "Carlos" in the student table
    And I should see "Luisa" in the student table

  @javascript
  Scenario: Student is redirected away from teacher dashboard
    Given I am on the SAIPA teacher dashboard for "SAIPATEST" logged in as "student1"
    Then I should see "You do not have permission"

  @javascript
  Scenario: Guest cannot access teacher dashboard
    Given I am not logged in
    When I navigate to the SAIPA teacher dashboard for course "SAIPATEST"
    Then I should be redirected to the login page

  @javascript
  Scenario: Teacher can see risk status badges for students
    Given I am on the SAIPA teacher dashboard for "SAIPATEST" logged in as "teacher1"
    When the SAIPA risk evaluation has run for course "SAIPATEST"
    Then I should see risk level badges for the enrolled students
