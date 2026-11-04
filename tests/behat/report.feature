@local @local_eventcalendar
Feature: Calendar reports
  In order to know how the calendars are used
  As a user with the capability to view the calendar reports
  I need the figures of each calendar

  # Other plugins can link the name of each calendar to a detailed report: the scenarios check only the
  # summary of this plugin.

  Background:
    Given the following "local_eventcalendar > calendars" exist:
      | name   |
      | Advent |
    And the following "users" exist:
      | username | firstname | lastname |
      | analyst  | Ada       | Analyst  |
    And the following "role assigns" exist:
      | user    | role                 | contextlevel | reference |
      | analyst | eventcalendarcurator | System       |           |

  @accessibility @javascript
  Scenario: A user with the capability sees the figures of the calendars
    Given I log in as "analyst"
    When I visit "/local/eventcalendar/report.php"
    Then I should see "Calendar reports"
    And I should see "Advent"
    And I should see "Unlocked days"
    And the page should meet accessibility standards

  Scenario: The plugin roles reach their pages without being site administrators
    Given the following "users" exist:
      | username    | firstname | lastname    |
      | coordinator | Cleo      | Coordinator |
    And the following "role assigns" exist:
      | user        | role                     | contextlevel | reference |
      | coordinator | eventcalendarcoordinator | System       |           |
    When I log in as "coordinator"
    And I visit "/local/eventcalendar/manage.php"
    Then I should see "Advent"
    And I visit "/local/eventcalendar/report.php"
    And I should see "Calendar reports"
