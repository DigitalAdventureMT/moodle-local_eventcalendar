@local @local_eventcalendar @javascript @accessibility
Feature: Accessibility of the event calendar pages
  In order to use the calendars with assistive technologies
  As a user or a manager
  I need the plugin pages to meet the accessibility standards

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Sara      | Student  | student@example.com |
    And the following "local_eventcalendar > calendars" exist:
      | name   |
      | Advent |
    And the following "local_eventcalendar > days" exist:
      | calendar | daynumber | notiftitle | content                |
      | Advent   | 1         | Day one    | <p>First surprise</p>  |
      | Advent   | 2         | Day two    | <p>Second surprise</p> |
    And the following "role assigns" exist:
      | user    | role          | contextlevel | reference |
      | student | coursecreator | System       |           |
    And the following "local_eventcalendar > access rules" exist:
      | calendar | ruletype | subject       |
      | Advent   | role     | coursecreator |

  Scenario: The pages for users meet the accessibility standards
    Given I log in as "student"
    When I visit "/local/eventcalendar/list.php"
    Then the page should meet accessibility standards
    And I click on "Advent" "link" in the "region-main" "region"
    And the page should meet accessibility standards
    And I click on "[role='listitem'] a.stretched-link" "css_element"
    And the page should meet accessibility standards

  Scenario: The management pages meet the accessibility standards
    # The plain text editor keeps the check on the plugin markup, not on the core rich text editor.
    Given the following "user preferences" exist:
      | user  | preference | value    |
      | admin | htmleditor | textarea |
    And I log in as "admin"
    When I visit "/local/eventcalendar/list.php"
    Then the page should meet accessibility standards
    And I visit "/local/eventcalendar/manage.php"
    And the page should meet accessibility standards
    And I click on "//tr[contains(., 'Advent')]//a[normalize-space(.)='Edit']" "xpath_element"
    # The check is limited to the main region, which holds the markup of the plugin: the rest of the page is
    # made of core elements (navigation, file picker dialog) checked by the core tests.
    And the "region-main" "region" should meet accessibility standards
    And I visit "/local/eventcalendar/manage.php"
    And I click on "Edit the access rules" "link" in the "Advent" "table_row"
    And the page should meet accessibility standards
    And I visit "/local/eventcalendar/manage.php"
    And I click on "Manage (2 days)" "link" in the "Advent" "table_row"
    And the page should meet accessibility standards
    And I click on "Edit Day 1" "link"
    And the page should meet accessibility standards
    # Preview window: modal dialog with an accessible name, closed with the Escape key. Its attributes are
    # checked one by one; the content of the frame is checked in the scenario of the preview window.
    And I press "Content preview"
    And "[role='dialog'][aria-label='Preview']" "css_element" should be visible
    And the "aria-modal" attribute of "[role='dialog'][aria-label='Preview']" "css_element" should contain "true"
    And the "title" attribute of "[role='dialog'][aria-label='Preview'] iframe" "css_element" should contain "Preview"
    And the "aria-label" attribute of "[role='dialog'][aria-label='Preview'] button" "css_element" should contain "Close"
    And I press the escape key
    And "[role='dialog'][aria-label='Preview']" "css_element" should not be visible
    And I visit "/local/eventcalendar/iconsets.php"
    And the page should meet accessibility standards
    And I visit "/local/eventcalendar/graphics.php"
    And the page should meet accessibility standards
    And I visit "/local/eventcalendar/notifications.php"
    And the page should meet accessibility standards
    And I visit "/local/eventcalendar/email_templates.php"
    And the page should meet accessibility standards

  Scenario: The calendar creation form and the reports meet the accessibility standards
    Given I log in as "admin"
    When I visit "/local/eventcalendar/manage.php?action=create"
    Then the "region-main" "region" should meet accessibility standards
    And I visit "/local/eventcalendar/report.php"
    And the page should meet accessibility standards

  Scenario: The notification set created from a pack meets the accessibility standards
    Given I log in as "admin"
    When I visit "/local/eventcalendar/notifications.php?action=create"
    Then the page should meet accessibility standards
    And I set the field "importpack" to "Advent calendar (English)"
    And I press "Load pack"
    And the page should meet accessibility standards
    And I press "Save changes"
    And I should see "Notification set saved."
    And I click on "//a[contains(@href, 'action=view')]" "xpath_element"
    And the page should meet accessibility standards

  Scenario: The email template form meets the accessibility standards
    # The structure preview is a frame sandboxed without scripts, where the accessibility check cannot run:
    # the scenario checks that the frame has an accessible name, then removes it and checks the rest of the page.
    Given I log in as "admin"
    When I visit "/local/eventcalendar/email_templates.php"
    And I click on "Edit" "link" in the "region-main" "region"
    Then "//iframe[@data-region='local-eventcalendar-emailpreview'][normalize-space(@title) != '']" "xpath_element" should exist
    And I remove the preview frames from the page
    And the page should meet accessibility standards

  Scenario: The preview window of a day shows the content and the email
    # The preview frame is sandboxed without scripts, because it shows HTML of the editors and of the email
    # templates. The accessibility check injects a script, so it cannot run inside the frame: the window is
    # checked in the scenario of the management pages and the markup of the content on the day page.
    Given the following "user preferences" exist:
      | user  | preference | value    |
      | admin | htmleditor | textarea |
    And I log in as "admin"
    When I visit "/local/eventcalendar/manage.php"
    And I click on "Manage (2 days)" "link" in the "Advent" "table_row"
    And I click on "Edit Day 1" "link"
    And I press "Content preview"
    And "[role='dialog'][aria-label='Preview']" "css_element" should be visible
    And I wait "2" seconds
    And I switch to "eventcalendar_preview_iframe" iframe
    Then I should see "First surprise"
    And I switch to the main frame
    And I press the escape key
    And I press "Email preview"
    And "[role='dialog'][aria-label='Preview']" "css_element" should be visible
    And I wait "2" seconds
    And I switch to "eventcalendar_preview_iframe" iframe
    And I should see "Admin"
    And I switch to the main frame

  Scenario: The subscription to the notifications and the unsubscribe page meet the accessibility standards
    Given I log in as "student"
    When I visit "/local/eventcalendar/list.php"
    And I click on "Advent" "link" in the "region-main" "region"
    And I press "Subscribe to notifications"
    Then the page should meet accessibility standards
    # An unsubscribe link with a wrong token shows the page with its error message.
    And I visit "/local/eventcalendar/unsubscribe.php?calendar=1&user=1&token=x"
    And the page should meet accessibility standards
