Feature:
  In order to test the failures produced by the FailAid package
  As a maintainer of the package
  I want to run the pack and see it in action

  Scenario:
    Given I am on the homepage
    And I record the state of the user
    Then I should see "Yo hello this will fail"