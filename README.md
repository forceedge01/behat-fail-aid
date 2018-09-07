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
        - FailAid\Context\FailureContext
```

Have a look at the options you can provide to the context. Any of the options can be used in conjunction.

screenshotDirectory option:
----------------------------

```gherkin
#behat.yml
...
- FailAid\Context\FailureContext:
    screenshotDirectory: /temp/failures/behat/screenshots/
```

Override default screenshot path.

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
      '/images/': 'http://dev.environment/images'
      '/js/': 'http://dev.environment/js/'
```

Applied on the content of a html screenshot. Useful when working with relative urls for assets.