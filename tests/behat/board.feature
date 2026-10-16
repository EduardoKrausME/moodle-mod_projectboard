@mod @mod_projectboard
Feature: Students manage their own Project Board
  In order to organise coursework
  As an enrolled student
  I need to create and view persistent project tasks

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Project Course | PROJECT101 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | learner1 | Learner | One | learner1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | learner1 | PROJECT101 | student |
    And the following "activities" exist:
      | activity | name | intro | course |
      | projectboard | Academic project | Project planning | PROJECT101 |

  @javascript
  Scenario: A learner views their project board
    Given I log in as "learner1"
    And I am on the "Academic project" "mod_projectboard > View" page
    Then I should see "Backlog"
    And I should see "To do"
    And I should see "In progress"
    And I should see "Review"
    And I should see "Done"
