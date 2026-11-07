@local @local_eventcalendar
Feature: Refusal of calendars and days that cannot be reached
  In order to keep unannounced calendars confidential
  As a user who cannot reach a calendar
  I should see the same generic page whether the calendar exists or not, without its name

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Sara      | Student  | student@example.com |
    And the following "local_eventcalendar > calendars" exist:
      | name            | forcehide |
      | Open calendar   | 0         |
      | Secret campaign | 0         |
      | Hidden calendar | 1         |
    And the following "local_eventcalendar > days" exist:
      | calendar        | daynumber |
      | Open calendar   | 1         |
      | Secret campaign | 1         |
      | Hidden calendar | 1         |
    And the following "local_eventcalendar > access rules" exist:
      | calendar        | ruletype | subject       |
      | Secret campaign | role     | coursecreator |

  Scenario Outline: An unreachable calendar is refused without showing its name
    When I am on the "<identifier>" "local_eventcalendar > <page>" page logged in as "student"
    Then I should see "Calendar not available."
    And I should not see "<calendar>"
    And the page title should contain "Event calendar"

    Examples:
      | page     | identifier          | calendar        |
      | Calendar | Secret campaign     | Secret campaign |
      | Day      | Secret campaign > 1 | Secret campaign |
      | Calendar | Hidden calendar     | Hidden calendar |
      | Day      | Hidden calendar > 1 | Hidden calendar |

  Scenario: Missing calendars and days get the same page as unreachable ones
    Given I log in as "student"
    When I visit "/local/eventcalendar/view.php?id=999999"
    Then I should see "Calendar not available."
    And I visit "/local/eventcalendar/view_day.php?id=999999&day=1"
    And I should see "Calendar not available."
    And I am on the "Open calendar > 99" "local_eventcalendar > Day" page
    And I should see "Calendar not available."
    And I should not see "Open calendar"

  Scenario: A reachable day shows the calendar name
    When I am on the "Open calendar > 1" "local_eventcalendar > Day" page logged in as "student"
    Then I should see "Open calendar"
    And I should not see "Calendar not available."

  Scenario: A manager can still open a day of a hidden calendar
    When I am on the "Hidden calendar > 1" "local_eventcalendar > Day" page logged in as "admin"
    Then I should see "Hidden calendar"
    And I should not see "Calendar not available."
