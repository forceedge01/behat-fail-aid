Feature:
  In order to test the failures produced by the FailAid package
  As a maintainer of the package
  I want to run the pack and see it in action

  Scenario: Just another scenario to test session bleed
    Given I am on the homepage
    And I record the state of the user
    Then I should see "This is a sample page for behat test."

  Scenario: Check error output, generates 3 screenshots.
    # Will fail as its being done before loading the page.
    Given I take a screenshot
    And I am on the homepage
    And I record the state of the user
    And I take a screenshot
    And I gather facts for the current state
    Then I should see "Yo hello this will fail"

  Scenario: Just another scenario to test session bleed
    Given I am on the homepage
    And I record the state of the user
    Then I should see "This is a sample page for behat test."