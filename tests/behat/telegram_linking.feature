@local_saipa @local_saipa_telegram
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
      | saipa     | Course       | SAIPATEST | course-view-*   | side-post     |
    And the SAIPA Telegram channel is enabled in settings

  @javascript
  Scenario: Student sees "Link Telegram" button when not linked
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student has not linked Telegram
    Then I should see "Link Telegram" in the "side-post" "region"

  @javascript
  Scenario: Student sees Telegram link button which opens Telegram deep-link
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student has not linked Telegram
    When I click on "Link my Telegram" "button" in the "side-post" "region"
    Then I should see a Telegram deep-link starting with "https://t.me/"
    And I should see "Waiting for confirmation"

  @javascript
  Scenario: Student sees "linked" status after Telegram is confirmed
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student "student1" has a confirmed Telegram link with username "carlos_test"
    Then I should see "Telegram linked" in the "side-post" "region"
    And I should see "@carlos_test"

  @javascript
  Scenario: Student can unlink Telegram
    Given I am on the "SAIPATEST" "course" page logged in as "student1"
    And the student "student1" has a confirmed Telegram link with username "carlos_test"
    When I click on "Unlink" "button" in the "side-post" "region"
    Then I should see "Link Telegram" in the "side-post" "region"
    And I should not see "@carlos_test"

  @javascript
  Scenario: Telegram section is hidden when Telegram channel is disabled in settings
    Given the SAIPA messaging channel is set to "none"
    And I am on the "SAIPATEST" "course" page logged in as "student1"
    Then I should not see "Link Telegram" in the "side-post" "region"
