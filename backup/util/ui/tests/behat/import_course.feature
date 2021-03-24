@core @core_backup
Feature: Import course's contents into another course
  In order to move and copy contents between courses
  As a teacher
  I need to import a course contents into another course selecting what I want to import

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
    And the following "permission overrides" exist:
      | capability         | permission | role           | contextlevel | reference |
      | enrol/manual:enrol | Allow      | teacher        | Course       | C1        |

  Scenario: Import course's contents to another course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Database" to section "1" and I fill the form with:
      | Name | Test database name |
      | Description | Test database description |
    And I add a "Forum" to section "2" and I fill the form with:
      | Forum name | Test forum name |
      | Description | Test forum description |
    And I add the "Comments" block
    And I add the "Recent blog entries" block
    And I turn editing mode off
    When I import "Course 1" course into "Course 2" course using this options:
    Then I should see "Test database name"
    And I should see "Test forum name"
    And I should see "Comments" in the "Comments" "block"
    And I should see "Recent blog entries"

  Scenario: Import process has permission option following default setting OFF
    Given I log in as "teacher1"
    And I am on "Course 2" course homepage
    When I navigate to "Import" in current page administration
    And I choose course import from "Course 1"
    And I should see "Include override permission"
    And the field "Include override permission" matches value ""
    And I press "Jump to final step"
    And I press "Continue"
    Then I navigate to "Users > Permissions" in current page administration
    And I should see "Non-editing teacher (0)"

  Scenario: Import process has permission option ON
    Given I log in as "admin"
    And I set the following administration settings values:
      | backup_import_permissions | 1 |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 2" course homepage
    When I navigate to "Import" in current page administration
    And I choose course import from "Course 1"
    And I should see "Include override permission"
    And the field "Include override permission" matches value "1"
    And I press "Jump to final step"
    And I press "Continue"
    Then I navigate to "Users > Permissions" in current page administration
    And I should see "Non-editing teacher (1)"
    And I set the field "Advanced role override" to "Non-editing teacher (1)"
    And I press "Go"
    And "enrol/manual:enrol" capability has "Allow" permission
