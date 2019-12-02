Feature:
  In order to test the failures produced by the FailAid package
  As a maintainer of the package
  I want to run the pack and see it in action

  Scenario: Check error output, generates 3 screenshots.
    Given I am on the homepage
    And I record the state of the user
    And I take a screenshot
    And I gather facts for the current state
    Then I should see "Yo hello this will fail"