Behat Fail Aid [ ![Codeship Status for forceedge01/behat-fail-aid](https://app.codeship.com/projects/0a2814f0-984a-0136-1935-7202d5d80573/status?branch=master)](https://app.codeship.com/projects/305220)
==============

Introduction
-------------

Time and time again we've all seen how difficult and stressful it can become to fix behat tests. This package is their to help gather
all possible information around failures and print them as you see a failure taking out the need to do basic investigations with minimal setup.

Usual failure
![Before](https://raw.githubusercontent.com/forceedge01/behat-fail-aid/master/extras/generic-from.png)

With fail-aid context
![After](https://raw.githubusercontent.com/forceedge01/behat-fail-aid/master/extras/generic-to.png)

With config options enabled
![More info](https://raw.githubusercontent.com/forceedge01/behat-fail-aid/master/extras/max-details.png)

The links are ready to be clicked on and opened in the browser. No faff!

You also get the following step definitions for free upon activation:

```gherkin
And I take a screenshot
And I gather facts for the current state
```

These will output relevant information on the screen. (Your formatting must be pretty for this to work --format=pretty).

Whats new:
----------

Major: Properly integrate extension with behat.

Minor: Define size for screenshot.

Patch: Import latest packages to fix security issues.

Installation:
-------------
```shell
composer require genesis/behat-fail-aid --dev
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
  extensions:
    FailAid\Extension: ~
```

This is the basic setup and will give you a lot of information on failures. For more options read through the rest of the README. Any of the options below can be used in conjunction with each other.

screenshot options:
----------------------------

```gherkin
#behat.yml
...
- FailAid\Extension:
    screenshot:
        directory: /temp/failures/behat/screenshots/
        mode: default
        autoClean: false
        size: 1444x1280
```

### directory (string):
Override default screenshot path. Default folder is provided by `sys_get_temp_dir()` function. Can be a relative path.

### mode (string): 
default: Drivers will produce a png where supported, html otherwise.

html: All drivers will produce html screenshots, useful for interrogating runtime code.

png: All drivers will produce png screenshots, or die trying.

### autoClean (bool):
Clean up the directory before the test suite runs.

### Size (string):
The size of the screenshot to be taken on failure. At present, does not reset the size of the window. May conflict with the maximiseWindow API.

siteFilters option:
--------------------

```gherkin
#behat.yml
...
- FailAid\Extension:
    siteFilters:
      '/images/': 'http://dev.environment/images/'
      '/js/': 'http://dev.environment/js/'
```

Applied on the content of a html screenshot. Useful when working with relative urls for assets.

Tracking js errors/logs:
------------------------

You can easily track JavaScript activity on the page whenever a fail occurs. Place the following snippet in the head section of your application (make sure its global):

```js
<script type="text/javascript">
  window.jsErrors = []; window.jsWarns = []; window.jsLogs = [];
  window.onerror = function(error, url, line) {
      window.jsErrors.push('[Uncaught error]: ' + error + '; Line: ' + line);
  };

  if (!console) var console = {};
  var _privateError = console.error;
  console.error = function() {
    window.jsErrors.push('[Console error]: ' + JSON.stringify(arguments)); _privateError.apply(console, arguments);
  }

  var _privateWarn = console.warn;
  console.warn = function() {
    window.jsWarns.push('[Console warn]: ' + JSON.stringify(arguments)); _privateWarn.apply(console, arguments);
  }

  var _privateLog = console.log;
  console.log = function() {
    window.jsLogs.push('[Console log]: ' + JSON.stringify(arguments)); _privateLog.apply(console, arguments);
  }
</script>
```
Your original debugging messages will still appear in your console.


```gherkin
#behat.yml
...
- FailAid\Extension:
    defaultSession: chrome # If you've got multiple sessions registered. If its a standard setup you won't need this.
    trackJs:
      errors: true
      warns: true
      logs: true
      trim: 1000
```

When errors is enabled, any intended console.error calls and js exceptions will be recorded and displayed as part of the failure. Trim is applied on messages to shorten to the specified length.

debugBarSelectors option:
-------------------------

```gherkin
#behat.yml
...
- FailAid\Extension:
    debugBarSelectors: #Only CSS selectors allowed.
      'Status Code': '#debugBar .statusCode'
      'Error Message': '#debugBar .errorMessage'
      'Queries Executed': '#debugBar .executedQueries'
```

The above will go through each of the selector and find the element. If the element is found, it will display the text contained in the failure output. The debug bar details are gather after taking a screenshot of the page, so its safe to navigate out to another page if needs be. If you have to do this, have a look at the 'Advanced Integration' section for more information.

defaultSession option:
----------------------

When you're using multiple sessions you can configure to use a particular session only with the fail aid extension.

```gherkin
...
- FailAid\Extension:
    defaultSession: mySession
...
```

Recording states:
-------------------------

You can record the state of your test for a failure. A state resets before each scenario.

```php
# FeatureContext.php
<?php

use FailAid\Context\FailureContext;

class FeatureContext
{
    /**
     * @Given I am logged in
     */
    public function login()
    {
        $email = $this->createUserWithRandomEmail(); // assume this returns abc@xyz.com
        $this->fillField('email', $email);
        $this->fillField('password', 'xxxxxxxx');
        $this->press('login');

        FailureContext::addState('test user', $email);
    }
}
```

When the above step definition is used in any scenario, it will record the test user email within the current state of the scenario. If the scenario fails, you will get any information stored in the state within the failure message.

```
...
[STATE]
  [TEST USER] abc@xyz.com
...
```

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
            'screenshot' => [
              'directory' => null,
              'mode' => FailureContext::SCREENSHOT_MODE_DEFAULT,
              'autoClean' => false,
            ],
            'siteFilters' => [],
            'debugBarSelectors' => [],
            'defaultSession' => 'mySession',
        ];

        $scope->getEnvironment()->registerContextClass(
            FailureContext::class,
            $params
        );
    }
}

```
