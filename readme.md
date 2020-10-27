# PSR-15 Authentication Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=master)](https://travis-ci.org/dakujem/auth-middleware)

Modern and flexible PSR-15 authentication middleware.

> 💿 `composer require dakujem/auth-middleware`



## Example

The following example uses Slim PHP framework.
```php
$app->add(AuthWizard::assertTokens($app->getResponseFactory()));
$app->add(AuthWizard::decodeTokens($secret));
```

```php
$mwFactory = AuthWizard::factory($secret, $app->getResponseFactory());
$app->add($mwFactory->decodeTokens());
$app->group('/foo')->add($mwFactory->assertTokens());
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

