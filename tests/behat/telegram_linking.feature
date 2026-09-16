@local @local_saipa @local_saipa_telegram
Feature: SAIPA Telegram account linking
  As a student
  I need to be able to link my Telegram account to SAIPA
  So that I receive alert notifications on Telegram

  Background:
    Given the following "courses" exist:
      | fullname   | shortname | category |
      | SAIPA Test | SAIPATEST | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Carlos    | Alumno   | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course    | role    |
      | student1 | SAIPATEST | student |
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | saipa     | Course       | SAIPATEST | course-view-*   | side-pre      |
    And the SAIPA Telegram channel is enabled in settings

  @javascript
  Scenario: Student sees "Link my Telegram" button when not linked
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student has not linked Telegram
    Then I should see "Link my Telegram" in the "SAIPA Assistant" "block"

  @javascript
  Scenario: Student sees Telegram link button which opens Telegram deep-link
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student has not linked Telegram
    And I install a Telegram deep-link listener
    When I click on "Link my Telegram" "button" in the "SAIPA Assistant" "block"
    Then I should see a Telegram deep-link starting with "https://t.me/"
    And I should see "Waiting for confirmation"

  @javascript
  Scenario: Student sees "linked" status after Telegram is confirmed
    Given the student "student1" has a confirmed Telegram link with username "carlos_test"
    And I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should see "Connected as @" in the "SAIPA Assistant" "block"
    And I should see "@carlos_test"

  @javascript
  Scenario: Student can unlink Telegram
    Given the student "student1" has a confirmed Telegram link with username "carlos_test"
    And I am on the "SAIPATEST" "course" page logged in as "student1"
    When I click on "Unlink" "button" in the "SAIPA Assistant" "block"
    Then I should see "Link my Telegram" in the "SAIPA Assistant" "block"
    And I should not see "@carlos_test"

  @javascript
  Scenario: Telegram section is hidden when Telegram channel is disabled in settings
    Given the SAIPA messaging channel is set to "none"
    And I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should not see "Link my Telegram" in the "SAIPA Assistant" "block"
