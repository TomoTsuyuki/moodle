@core @core_backup
Feature: Restore random question

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |

  @javascript @_file_upload
  Scenario: Restore the quiz from 3.9 containing random questions
    Given I am on the "Course 1" "restore" page logged in as "admin"
    And I press "Manage course backups"
    And I upload "backup/moodle2/tests/fixtures/test_random_question_39.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "test_random_question_39.mbz" backup into "Course 1" course using this options:
    When I am on the "Test MDL-78902 quiz" "mod_quiz > edit" page logged in as admin
    Then I should see "See questions"
    And I click on "See questions" "link"
    And I should see "Test MDL-78902 T/F question"
