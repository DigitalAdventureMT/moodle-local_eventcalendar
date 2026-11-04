@local @local_eventcalendar @javascript
Feature: Publishing code of a calendar
  In order to publish a calendar in pages and blocks
  As a manager
  I need ready-made code to copy, shown three options at a time

  # Other plugins can add options after these three, so the scenarios check only the three options of the
  # plugin and never the carousel: with three options all of them fit in one page and the carousel is hidden.

  Background:
    Given the following "local_eventcalendar > calendars" exist:
      | name   |
      | Advent |
    And I log in as "admin"
    And I visit "/local/eventcalendar/manage.php"

  @accessibility
  Scenario: The publishing dialog shows the options of the plugin
    When I click on "Publishing code" "button" in the "Advent" "table_row"
    Then "dialog[open]" "css_element" should be visible
    And I should see "Publish the calendar: Advent" in the "dialog[open]" "css_element"
    And I should see "Link" in the "dialog[open]" "css_element"
    And I should see "Card with banner" in the "dialog[open]" "css_element"
    And I should see "Button" in the "dialog[open]" "css_element"
    And the "dialog[open]" "css_element" should meet accessibility standards
    And I press the escape key
    And "dialog[open]" "css_element" should not exist

  Scenario: The dialog is closed with its close button
    When I click on "Publishing code" "button" in the "Advent" "table_row"
    And I click on "Close" "button" in the "dialog[open]" "css_element"
    Then "dialog[open]" "css_element" should not exist
