@local @local_assignbulk @javascript
Feature: Upload a file that contains valid data
  In order to easily submit on behalf of many students
  As a teacher
  I need to upload a zip file containing their submissions.

Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "permission overrides" exist:
      | capability | permission | role | contextlevel | reference |
      | mod/assign:editothersubmission | Allow | editingteacher | Course | C1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Theo | Teacher | teacher1@example.com |
      | user01 | Student | 01 | student01@example.com |
      | user02 | Student | 02 | student02@example.com |
      | user03 | Student | 03 | student03@example.com |
      | user04 | Student | 04 | student04@example.com |
      | user05 | Student | 05 | student05@example.com |
      | user06 | Student | 06 | student06@example.com |
      | user07 | Student | 07 | student07@example.com |
      | user08 | Student | 08 | student08@example.com |
      | user09 | Student | 09 | student09@example.com |
      | user10 | Student | 10 | student10@example.com |
      | user11 | Student | 11 | student11@example.com |
      | user12 | Student | 12 | student12@example.com |
      | user13 | Student | 13 | student13@example.com |
      | user14 | Student | 14 | student14@example.com |
      | user15 | Student | 15 | student15@example.com |
      | user16 | Student | 16 | student16@example.com |
      | user17 | Student | 17 | student17@example.com |
      | user18 | Student | 18 | student18@example.com |
      | user19 | Student | 19 | student19@example.com |
      | user20 | Student | 20 | student20@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | user01 | C1 | student |
      | user02 | C1 | student |
      | user03 | C1 | student |
      | user04 | C1 | student |
      | user05 | C1 | student |
      | user06 | C1 | student |
      | user07 | C1 | student |
      | user08 | C1 | student |
      | user09 | C1 | student |
      | user10 | C1 | student |
      | user11 | C1 | student |
      | user12 | C1 | student |
      | user13 | C1 | student |
      | user14 | C1 | student |
      | user15 | C1 | student |
      | user16 | C1 | student |
      | user17 | C1 | student |
      | user18 | C1 | student |
      | user19 | C1 | student |
      | user20 | C1 | student |
    And the following "activities" exist:
      | activity | idnumber | course | name | assignsubmission_file_enabled | assignsubmission_file_maxfiles | assignsubmission_file_maxsizebytes |
      | assign   | A1       | C1     | A1   | 1 | 10 | 1024 |
    # And I log in as "teacher1"
    # And I am on "Course 1" course homepage with editing mode on
    # And I add a "Assignment" to section "1" and I fill the form with:
    #   | Assignment name | Test assignment name |
    #   | Description | Submit file |
    #   | assignsubmission_file_enabled | 1 |

@javascript @_file_upload
Scenario: Upload a file that contains valid data
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "A1"
    And I navigate to "Bulk upload submissions" in current page administration
    And I upload "local/assignbulk/tests/fixtures/top_level_zip/all.zip" file to "File submissions" filemanager
    And I select "Username" from the "Identify user by" singleselect

    When I click on "Save changes" "button"

    Then I should see "Upload complete"
    And I should not see "The following files were not processed"
    And I should see "/user01.txt"
    And I should see "/user20.txt"
