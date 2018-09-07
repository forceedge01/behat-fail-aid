Introduction
-------------

Time and time again we've all seen how difficult and stressful it can become to fix behat tests. This package is their to help gather
all possible information around failures and print them as you see a failure taking out the need to do basic investigations.

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
        - FailureAid/Context/FailureContext:
            - screenshotDirectory: /temp/failures/behat/screenshots/
```

The screenshot directory specification is optional and you may omit it. The default system temp directory will be used in this case.