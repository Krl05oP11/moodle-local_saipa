@local @local_saipa @local_saipa_advisor
Feature: SAIPA advisor dashboard tabs
  As a pedagogical advisor
  I need every tab of the advisor dashboard to open and load its data
  So that the dashboard works with Bootstrap 4 (Moodle 4.4 and 4.5) and Bootstrap 5 (Moodle 5.x)

  Background:
    Given the following "courses" exist:
      | fullname   | shortname | category |
      | SAIPA Test | SAIPATEST | 0        |

  @javascript
  Scenario: Each tab of the advisor dashboard opens its pane and loads its data
    Given I log in as "admin"
    When I visit "/local/saipa/advisor.php"
    Then I should see "Active courses" in the "#tab-summary-content" "css_element"
    When I click on "#tab-risk-link" "css_element"
    Then I should see "Alert funnel" in the "#tab-risk-content" "css_element"
    And "#tab-summary" "css_element" should not be visible
    When I click on "#tab-engagement-link" "css_element"
    Then I should see "Per-course usage" in the "#tab-engagement-content" "css_element"
    And "#tab-risk" "css_element" should not be visible
    When I click on "#tab-controls-link" "css_element"
    Then I should see "Enable all" in the "#tab-controls-content" "css_element"
    When I click on "#tab-config-link" "css_element"
    Then I should see "Risk Thresholds" in the "#tab-config-content" "css_element"
    And "#tab-controls" "css_element" should not be visible
