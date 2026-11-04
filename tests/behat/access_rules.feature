@local @local_eventcalendar
Feature: Access rules of a calendar
  In order to show a calendar only to some users
  As a manager
  I need to define groups of access conditions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | creator  | Carla     | Creator  | creator@example.com  |
      | student  | Sara      | Student  | student@example.com  |
    And the following "role assigns" exist:
      | user    | role          | contextlevel | reference |
      | creator | coursecreator | System       |           |
    And the following "local_eventcalendar > calendars" exist:
      | name    |
      | Advent  |

  Scenario: Without access rules every user sees the calendar
    When I log in as "student"
    And I visit "/local/eventcalendar/list.php"
    Then I should see "Advent"

  @javascript
  Scenario: A manager restricts the calendar to a system role
    Given I log in as "admin"
    And I visit "/local/eventcalendar/manage.php"
    And I should see "Open to all" in the "Advent" "table_row"
    And I click on "Edit the access rules" "link" in the "Advent" "table_row"
    When I press "Add group of conditions"
    And I press "Add condition"
    And I set the field "Condition type" to "System role"
    And I set the field "Subject" to "Course creator"
    And I set the field "Comparison" to "is assigned"
    And I click on "Save rules" "button" in the "[data-region='groups'] + div" "css_element"
    Then I should see "Access rules saved."
    And I visit "/local/eventcalendar/manage.php"
    And I should see "1 group" in the "Advent" "table_row"
    And I log out
    And I log in as "creator"
    And I visit "/local/eventcalendar/list.php"
    And I should see "Advent"
    And I log out
    And I log in as "student"
    And I visit "/local/eventcalendar/list.php"
    And I should not see "Advent"

  @javascript
  Scenario: An incomplete condition cannot be saved
    Given I log in as "admin"
    And I visit "/local/eventcalendar/manage.php"
    And I click on "Edit the access rules" "link" in the "Advent" "table_row"
    When I press "Add group of conditions"
    And I press "Add condition"
    And I set the field "Condition type" to "System role"
    And I click on "Save rules" "button" in the "[data-region='groups'] + div" "css_element"
    Then I should see "Some conditions are incomplete: complete or delete them before saving."
    And I should see "Incomplete condition."

  @javascript
  Scenario: Removing the only group opens the calendar to all users again
    Given the following "local_eventcalendar > access rules" exist:
      | calendar | ruletype | subject       |
      | Advent   | role     | coursecreator |
    And I log in as "student"
    And I visit "/local/eventcalendar/list.php"
    And I should not see "Advent"
    And I log out
    And I log in as "admin"
    And I visit "/local/eventcalendar/manage.php"
    And I click on "Edit the access rules" "link" in the "Advent" "table_row"
    When I press "Delete the group and all its conditions"
    And I click on "Save rules" "button" in the "[data-region='groups'] + div" "css_element"
    Then I should see "Access rules saved."
    And I log out
    And I log in as "student"
    And I visit "/local/eventcalendar/list.php"
    And I should see "Advent"

  @javascript
  Scenario: A condition that refers to a deleted item is flagged and blocks access
    Given the following "local_eventcalendar > access rules" exist:
      | calendar | ruletype | subject |
      | Advent   | cohort   | 999999  |
    And I log in as "admin"
    And I visit "/local/eventcalendar/manage.php"
    Then "Some conditions are no longer valid" "icon" should exist in the "Advent" "table_row"
    And I click on "Edit the access rules" "link" in the "Advent" "table_row"
    And I should see "The chosen item has been deleted from Moodle or is no longer available."
    And I log out
    And I log in as "student"
    And I visit "/local/eventcalendar/list.php"
    And I should not see "Advent"
