# PSR-15 Auth Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=main)](https://travis-ci.org/dakujem/auth-middleware)

Modern and highly flexible PSR-15 authentication and authorization middleware.

> 💿 `composer require dakujem/auth-middleware`


The package consists of two decoupled middleware implementations:
- `TokenMiddleware` for token decoding
- `PredicateMiddleware` for token assertion (auth)


## Default Usage

Use `AuthWizard` for convenience:
```php
/* @var Slim\App $app */
$app->add(AuthWizard::assertTokens($app->getResponseFactory()));
$app->add(AuthWizard::decodeTokens('a-secret-api-key-never-to-commit-to-a-repo'));
```
For highly flexible options to instantiate the middleware, read the next chapter.

The pair of middleware (MW) will look for a [JWT](https://jwt.io/introduction/)
in the `Authorization` header or `token` cookie.\
Then it will decode it and inject the decoded payload to the `token` request attribute,
accessible to the application.\
If the token is not present or is not valid, the execution pipeline will be terminated
and a `401 Unauthorized` response will be returned.

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

For the defaults to work (the decoder in particular),
you need to install [Firebase JWT](https://github.com/firebase/php-jwt) package.\
`composer require firebase/php-jwt:"^5.0"`

>
> 💡
>
> You are able to use any other decoder implementation, see below.
>
> The MW can also be used for OAuth tokens or other tokens,
> simply by swapping the default decoder for another one.
>
> The examples use [Slim PHP](https://www.slimframework.com) framework,
> but same applies to any [PSR-15](https://www.php-fig.org/psr/psr-15/) compatible middleware dispatcher.
>


## Compose Your Own Middleware

In the examples above, we are using the [`AuthWizard`] convenience helper which provides sensible defaults.\
However, it is possible and encouraged to build your own middleware using the components provided by the package.

>
> Note that I'm using aliased class names instead of full interface names in this documentation for brevity.
>
> Here are the full interface names:
> - `Request` --> `Psr\Http\Message\ServerRequestInterface`\
> - `Response` --> `Psr\Http\Message\ResponseInterface`\
> - `ResponseFactory` --> `Psr\Http\Message\ResponseFactoryInterface`\
> - `Handler` --> `Psr\Http\Server\RequestHandlerInterface`\
> - `Logger` --> `Psr\Log\LoggerInterface`
>


### `TokenMiddleware`

The [`TokenMiddleware`] is responsible for finding and decoding a token,
then making it available to the rest of the app.

The [`TokenMiddleware`] is composed of
- a set of _extractors_
    - an extractor is responsible for finding and extracting a token from a Request, or return `null`
    - executed in sequence until one returns a string
    - `fn(Request,Logger):?string`
- a _decoder_
    - a decoder takes the raw token and decodes it
    - must only return a valid token object or `null`
    - `fn(string,Logger):?object`
- a _writer_
    - a writer takes a decoded token and injects it into the Request
    - receives `null` when no token has been found or an error has occurred
    - can do any other operation with the token or Request
    - `fn(?object,Request,Logger):Request`

Any of these callable components can be replaced or extended.\
The default components offer customization too.

These are the defaults provided by `AuthWizard::decodeTokens`:
```php
new TokenMiddleware(
    // decode JWT tokens
    new FirebaseJwtDecoder('a-secret-never-to-commit', ['HS256', 'HS512', 'HS384']),
    [
        // look for the tokens in the `Authorization` header
        TokenManipulators::headerExtractor('Authorization'),
        // look for the tokens in the `token` cookie
        TokenManipulators::cookieExtractor('token'),
    ],
    // disclose the decoded token using the `token` attribute
    TokenManipulators::attributeWriter('token')
);
```
The decoder should be swapped if you want to use OAuth tokens or a different JWT implementation.


### `PredicateMiddleware`

The [`PredicateMiddleware`] is a general purpose middleware that is only responsible for evaluation of a predicate and
termination of the pipeline execution in case the predicate fails.\
A pipeline is terminated by calling an error Handler instead of the next pipeline Handler.\
We may use it to assert that a token is indeed available in the Request.

The [`PredicateMiddleware`] is composed of
- a _predicate_
    - a _predicate_ is a callable that returns truthy if the predicate passes and falsy if not
    - `fn(Request):bool`
- an _error handler_
    - a PSR `Handler` implementation instance

Again, these components can be replaced, extended or customized.

These are the defaults provided by `AuthWizard::assertTokens`:
```php
new PredicateMiddleware(
    // look for the decoded token in the `token` attribute
    TokenManipulators::attributeTokenProvider('token'),
    // respond with 401 on error by default
    TokenManipulators::callableToHandler(
        TokenManipulators::basicErrorResponder(/* ResponseFactory */ $responseFactory, 401)
    )
);
```

You now have the flexibility to fine-tune the pair of MW for any purpose.


### `TokenManipulators`

The [`TokenManipulators`] static class provides various request/response manipulators
that ca be used for token handling.


### `FirebaseJwtDecoder`

The [`FirebaseJwtDecoder`] class serves as the default implementation for JWT token decoding.\
It is used as a _decoder_ for the `TokenMiddleware`.


### `AuthWizard`, `AuthFactory`

[`AuthWizard`] is a friction reducer that helps quickly instantiate the middleware with sensible defaults.

[`AuthFactory`] is a configurable factory provided for convenience. `AuthWizard` internally instantiates it.


## Testing

Run unit tests using the following command:

`$` `composer test`


## Contributing

Ideas, feature requests and other contribution is welcome.
Please send a PR or create an issue.




[`TokenMiddleware`]:      src/TokenMiddleware.php
[`PredicateMiddleware`]:  src/PredicateMiddleware.php
[`TokenManipulators`]:    src/TokenManipulators.php
[`FirebaseJwtDecoder`]:   src/FirebaseJwtDecoder.php
[`AuthWizard`]:           src/Support/AuthWizard.php
[`AuthFactory`]:          src/Support/AuthFactory.php

