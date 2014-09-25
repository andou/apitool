# PHP INI Files reader

A simple PHP class for API calls

## Installation

Add a dependency on `andou/apitool` to your project's `composer.json` file if you use [Composer](http://getcomposer.org/) to manage the dependencies of your project.
You have to also add the relative repository.

Here is a minimal example of a `composer.json` file that just defines a dependency on `andou/apitool`:

```json
{
    "require": {
        "andou/apitool": "*"
    },
    "repositories": [
    {
      "type": "git",
      "url": "https://github.com/andou/apitool.git"
    }
  ],
}
```    

## Usage Examples
You can use `andou/apitool` in your project this way

```php
require_once './vendor/autoload.php';
$api = Andou\Api::getInstance();
echo $api
        ->setApiAddress("http://your.web.address")
        ->apiCallYourMethod(
                array(
                    'param' => 'param_value',
                )
);
```

