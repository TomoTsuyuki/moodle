@core @core_backup
Feature: Backup settings
  In order to show default settings and notes
  As a site admin
  I need to see backup settings and notes

  Background:
    Given I log in as "admin"

  Scenario: Available backup permission option with notes
    When I navigate to "Courses > Backups > General backup defaults" in site administration
    Then I should see "backup_general_permissions"
    And I should see "PLEASE NOTE: Enable this setting will copy the permissions by role."
    And the field "Include override permissions" matches value "1"

  Scenario: Available import permission option with notes
    When I navigate to "Courses > Backups > General import defaults" in site administration
    Then I should see "backup_import_permissions"
    And I should see "PLEASE NOTE: Enable this setting will copy the permissions by role."
    And the field "Include override permissions" matches value "0"

  Scenario: Available automated backup permission option with notes
    When I navigate to "Courses > Backups > Automated backup setup" in site administration
    Then I should see "backup_auto_permissions"
    And I should see "PLEASE NOTE: Enable this setting will copy the permissions by role."
    And the field "Include override permissions" matches value "1"

  Scenario: Available restore permission option with notes
    When I navigate to "Courses > Backups > General restore defaults" in site administration
    Then I should see "restore_general_permissions"
    And I should see "PLEASE NOTE: Enable this setting will copy the permissions by role."
    And the field "Include override permissions" matches value "1"
