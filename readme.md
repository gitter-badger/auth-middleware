# PSR-15 Authentication Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=master)](https://travis-ci.org/dakujem/auth-middleware)

Modern and highly flexible PSR-15 authentication middleware.

> 💿 `composer require dakujem/auth-middleware`


The package consists of two decoupled middleware implementations:
- [`TokenMiddleware`] for token decoding
- [`PredicateMiddleware`] for token assertion (auth)


## Default Usage

```php
use Dakujem\Middleware\Support\AuthWizard;
```

The following example uses Slim PHP framework, but same applies to any PSR-15 compatible middleware dispatcher.
```php
/* @var Slim\App $app */
$app->add(AuthWizard::assertTokens($app->getResponseFactory()));
$app->add(AuthWizard::decodeTokens('a-secret-api-key-never-to-commit-to-a-repo'));
```
> 💡 The above uses a static helper wizard for convenience, but can be fine-tuned for any use case, see below.

The pair of middleware (MW) will look for a JWT token in the `Authorization` header or `token` cookie.\
Then it will decode it and put the decoded token to the `token` request attribute accessible for the application.\
If the token is not present or is not valid, the execution pipeline will be terminated
and a `403 Forbidden` response will be returned.

The token can be accessed via the request attribute:
```php
/* @var Request $request */
$decodedToken = $request->getAttribute('token');
```

The assertion can be applied to selected routes instead of every route:
```php
$mwFactory = AuthWizard::factory($secret, $app->getResponseFactory());
$app->add($mwFactory->decodeTokens());                // decode the token for all routes, but
$app->group('/foo')->add($mwFactory->assertTokens()); // only apply the assertion for selected ones
```

For the defaults to work, you need to install Firebase JWT package.\
`composer require firebase/php-jwt:"^5.0"`

> 💡 You are able to use any other implementation, see below.


## Compose your own middleware

The [`TokenMiddleware`] is responsible for finding and decoding a token,
then making it available to the rest of the app.

The [`TokenMiddleware`] is composed of
- a set of _extractors_
    - an _extractor_ is responsible for finding and extracting a token from a Request, or return `null`
    - `fn(Request,Logger):?string`
- a _decoder_
    - a _decoder_ takes the raw token and decodes it
    - must only return a valid token object or `null`
    - `fn(string,Logger):?object`
- a _writer_
    - a _writer_ takes a decoded token and injects it into the Request
    - can do any other operation with the token or Request
    - `fn(?object,Request,Logger):Request`

Any of these callable components can be replaced or extended.\
The default components offer some customization too.

These are the defaults:
```php
new TokenMiddleware(
    new FirebaseJwtDecoder('a-secret-never-to-commit', ['HS256', 'HS512', 'HS384']),
    [
        TokenMiddleware::headerExtractor('Authorization'),
        TokenMiddleware::cookieExtractor('token'),
    ],
    TokenMiddleware::attributeWriter('token')
);
```

The [`PredicateMiddleware`] is a general purpose middleware that is only responsible for evaluation of a predicate and
termination of the pipeline execution (by not calling the next layer) in case the predicate fails.\
Here we are using it to assert that a token is indeed available in the Request.

The [`PredicateMiddleware`] is composed of
- a _predicate_
    - a _predicate_ is a callable that returns truthy if the predicate passes and falsy if not
    - `fn(Request):bool`
- an _error responder_
    - a responder is a callable that takes a Request and returns a Response
    - `fn(Request):Response`

Again, these components can be replaced, extended or customized.

These are the defaults:
```php
new PredicateMiddleware(
    TokenCallables::attributeTokenProvider('token'),
    PredicateMiddleware::basicErrorResponder( /* ResponseFactory */ $responseFactory )
);
```

You now have the flexibility to fine-tune the pair of MW for any purpose.

>
> Note that I'm using aliased class names instead of full interface names in this documentation for brevity.
>
> Here are the full interface names:
> - `Request` --> `Psr\Http\Message\ServerRequestInterface`\
> - `Response` --> `Psr\Http\Message\ResponseInterface`\
> - `ResponseFactory` --> `Psr\Http\Message\ResponseFactoryInterface`
> - `Logger` --> `Psr\Log\LoggerInterface`
>


## Testing

Run unit tests using the following command:

`$` `composer test`


## Contributing

Ideas or contribution is welcome. Please send a PR or file an issue.



[`TokenMiddleware`]: src/TokenMiddleware.php
[`PredicateMiddleware`]: src/PredicateMiddleware.php

