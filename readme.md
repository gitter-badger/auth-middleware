# PSR-15 Authentication Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=master)](https://travis-ci.org/dakujem/auth-middleware)

Modern and flexible PSR-15 authentication middleware.

> 💿 `composer require dakujem/auth-middleware`



## Example

The following example uses Slim PHP framework.
```php
//SlimWizard::addJwtAuthentication($app, $secret);

// TODO need a friction reducer 🤷‍♂️


$mwFactory = new SlimAuthFactory($secret, $app->getResponseFactory());
$app->add($mwFactory->auth());
$app->add($mwFactory->tokens());

$app->group('/foo')->add($mwFactory->auth());
```

This call is equivalent to the following:
```php
$app->add(new PredicateMiddleware(
    TokenCallables::attributeTokenProvider(),
    PredicateMiddleware::basicErrorResponder($app->getResponseFactory()))
);
$app->add(new TokenMiddleware(new FirebaseJwtDecoder($secret)));
```
The above can be fine-tuned for any use.


## Testing

Run unit tests using the following command:

`$` `composer test`


## Contributing

Ideas or contribution is welcome. Please send a PR or file an issue.

