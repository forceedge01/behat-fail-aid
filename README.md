Introduction
-------------

Time and time again we've all seen how difficult and stressful it can become to fix behat tests. This package is their to help gather
all possible information around failures and print them as you see a failure taking out the need to do basic investigations with minimal setup.

Installation:
-------------
```shell
composer require genesis/behat-fail-aid
```

Usage:
------

```gherkin
#behat.yml
default:
  suites:
    default:
      contexts:
        - FailAid\Context\FailureContext
```

This is the basic setup and will give you a lot of information on failures. For more options read through the rest of the README. Any of the options below can be used in conjunction with each other.

screenshotDirectory option:
----------------------------

```gherkin
#behat.yml
...
- FailAid\Context\FailureContext:
    screenshotDirectory: /temp/failures/behat/screenshots/
```

Override default screenshot path. Default folder is provided by `sys_get_temp_dir()` function.

screenshotMode option:
------------------------

```gherkin
#behat.yml
...
- FailAid\Context\FailureContext:
    screenshotMode: default
```

default: Selenium2 enabled drivers will produce a png, anything else will produce html screenshots.
html: All drivers will produce html screenshots, useful for interrogating runtime code.

siteFilters option:
--------------------

```gherkin
#behat.yml
...
- FailAid\Context\FailureContext:
    siteFilters:
      '/images/': 'http://dev.environment/images/'
      '/js/': 'http://dev.environment/js/'
```

Applied on the content of a html screenshot. Useful when working with relative urls for assets.

debugBarSelectors option:
-------------------------

```gherkin
#behat.yml
...
- FailAid\Context\FailureContext:
    debugBarSelectors: #Only CSS selectors allowed.
      'Status Code': '#debugBar .statusCode'
      'Error Message': '#debugBar .errorMessage'
      'Queries Executed': '#debugBar .executedQueries'
```

The above will go through each of the selector and find the element. If the element is found, it will display the text contained in the failure output. The debug bar details are gather after taking a screenshot of the page, so its safe to navigate out to another page if needs be. If you have to do this, have a look at the 'Advanced Integration' section for more information.

Common debugging issues:
-------------------------

Its very common for a debug bar to interfere with your tests i.e 'your click will be received by another element' when performing JS enabled behaviour tests. In those cases, I would advise not to turn the debug bar off, but to execute code to hide it instead. In terms of debugging, gathering as much information as possible is paramount to a speedy fix. I would suggest placing your `hideDebugBar()` code after a visit call. This could be as simple as clicking a hide button on the bar.

Advanced integration:
----------------------

Sometimes your logic will be more complicated and passing in options may not work for you. In those cases, it is advisable to have a look at the FailureContext of what it allows you to override. You can extend the FailureContext with your own context class, and override parts that you deem necessary. You will have to register your own class with the behat.yml contexts section.

To register with all suites without separate configuration, or just doing it in code:

```php
# FeatureContext.php
<?php

use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use FailAid\Context\FailureContext;

class FeatureContext
{
    /**
     * @BeforeSuite
     */
    public static function loadFailureContext(BeforeSuiteScope $scope)
    {
        $params = [
            'screenshotDirectory' => null,
            'screenshotMode' => FailureContext::SCREENSHOT_MODE_DEFAULT,
            'siteFilters' => []
        ];

        $scope->getEnvironment()->registerContextClass(
            FailureContext::class,
            $params
        );
    }
}

```
