# PSR-15 Auth Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=main)](https://travis-ci.org/dakujem/auth-middleware)
[![Coverage Status](https://coveralls.io/repos/github/dakujem/auth-middleware/badge.svg?branch=main)](https://coveralls.io/github/dakujem/auth-middleware?branch=main)

Modern and highly flexible PSR-15 authentication and authorization middleware.

> 💿 `composer require dakujem/auth-middleware`


## Default Usage

The package makes use of _two_ decoupled middleware implementations:
- `TokenMiddleware` for (JWT) token decoding
- `GenericMiddleware` for token assertion (authentication & authorization)

Use `AuthWizard` for convenience:
```php
/* @var Slim\App $app */
$app->add(AuthWizard::assertTokens($app->getResponseFactory()));
$app->add(AuthWizard::decodeTokens('a-secret-api-key-never-to-commit'));
```

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
$mwFactory = AuthWizard::factory('a-secret-api-key-never-to-commit', $app->getResponseFactory());
$app->add($mwFactory->decodeTokens());                     // decode the token for all routes, but
$app->group('/foo', ...)->add($mwFactory->assertTokens()); // only apply the assertion for selected ones
```

Custom token inspection can be applied too:
```php
$app->group('/admin', ...)->add(AuthWizard::inspectTokens(
    $app->getResponseFactory(),
    function(MyToken $token, $next, $withError): Response {
        return $token->grantsAdminAccess() ? $next() : $withError('Admin privilege required!');
    }
));
```
> To cast the token to a specific class as seen above,
> custom _decoder_ must be used for `TokenMiddleware`, see the next chapters.

For highly flexible options to instantiate the middleware, read the "Compose Your Own Middleware" chapter below.

For the defaults to work (the decoder in particular),
you need to install [Firebase JWT](https://github.com/firebase/php-jwt) package.\
`composer require firebase/php-jwt:"^5.0"`

>
> 💡
>
> You are able to use any other decoder implementation and need not install Firebase JWT package, see below.
>
> The MW can also be used for OAuth tokens or other tokens,
> simply by swapping the default decoder for another one.
>
> The examples use [Slim PHP](https://www.slimframework.com) framework,
> but same applies to any [PSR-15](https://www.php-fig.org/psr/psr-15/) compatible middleware dispatcher.
>


## Extracting & Decoding JWT

```php
AuthWizard::decodeTokens(
    'a-secret-api-key-never-to-commit',
    'token',          // what attribute to put the decoded token to
    'Authorization',  // what header to look for the Bearer token in
    'token',          // what cookie to look for the raw token in
    'token.error'     // what attribute to write error messages to
);
```
The above creates an instance of `TokenMiddleware` that uses the default JWT decoder
and injects the decoded token to the `token` Request attribute accessible further in the app stack.

If the decoded token appears in the attribute, it is:
- present (obviously)
- authentic (has been created using the key)
- valid (not expired)


## Authorization

The middleware above will only decode the token, if present, authentic and valid,
but will NOT terminate the pipeline in any case❗

The authorization must be done by a separate middleware:
```php
AuthWizard::assertTokens(
    $responseFactory, // PSR-17 Request factory
    'token',          // what attribute to look for the decoded token in
    'token.error'     // what attribute to look for error messages in
);
```
The above creates a middleware that will assert that the `token` attribute of the Request contains a decoded token.\
Otherwise, the pipeline will be terminated and 401 (Unauthorized) Response returned.
An error message will be encoded as JSON into the response.

As you can see, the pair of middleware acts as a couple, but is decoupled for flexibility.

The middleware created by `AuthWizard::assertTokens` asserts the _presence_ of the decoded token only.\
It is possible to create custom inspections, of course:
```php
$inspector = function (object $token, callable $next, callable $withError): Response {
    if ($token->sub === 42) {        // Implement your inspection logic here.
        return $next();              // Invoke the next middleware for valid tokens
    }                                // or
    return $withError('Bad token.'); // return an error response for invalid ones.
};
AuthWizard::inspectTokens(
    $responseFactory, // PSR-17 Request factory
    $inspector,
    'token',          // what attribute to look for the decoded token in
    'token.error'     // what attribute to look for error messages in
);
```
In this case, the pipeline can be terminated on other conditions as well.
Custom error messages or data can be passed to the Response.\
If the token is not present, the middleware acts the same as the one created by `assertTokens`
and the inspector is not called.

You are of course able to cast the token to a custom class,
with methods like `MyToken::grantsAdminAccess` to tell if the token authorizes the user for admin access.
```php
AuthWizard::inspectTokens(
    $responseFactory,
    function(MyToken $token, $next, $withError): Response {
        return $token->grantsAdminAccess() ? $next() : $withError('Admin privilege required!');
    }
);
```


## Compose Your Own Middleware

In the examples above, we are using the [`AuthWizard`] helper which provides sensible defaults.\
However, it is possible and encouraged to build your own middleware using the components provided by this package.

You have the flexibility to fine-tune the middleware for any use case.

>
> Note that I'm using aliased class names instead of full interface names in this documentation for brevity.
>
> Here are the full interface names:
>
> | Alias | Full class name |
> |:------|:----------------|
> | `Request` | `Psr\Http\Message\ServerRequestInterface` |
> | `Response` | `Psr\Http\Message\ResponseInterface` |
> | `ResponseFactory` | `Psr\Http\Message\ResponseFactoryInterface` |
> | `Handler` | `Psr\Http\Server\RequestHandlerInterface` |
> | `Logger` | `Psr\Log\LoggerInterface` |
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
    - the decoder takes the raw token and decodes it
    - must only return a valid token object or `null`
    - `fn(string,Logger):?object`
- an _injector_
    - the injector is responsible for decorating the Request with the decoded token or error messages
    - obtains the decoded token by running the callable passed to its first argument, which is `fn():?object`
    - `fn(callable,Request,Logger):Request`

Any of these callable components can be replaced or extended.\
The default components offer customization too.

Here are the defaults provided by `AuthWizard::decodeTokens`:
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
    // target the `token` and `token.error` attributes for writing the decoded token or error message
    TokenManipulators::attributeInjector('token', 'token.error')
);
```
The decoder should be swapped if you want to use OAuth tokens or a different JWT implementation.
Exceptions may be caught and processed by the injector.


### `GenericMiddleware`

The [`GenericMiddleware`] is a general purpose middleware that turns a callable into PSR-15 implementation.
It accepts _any_ callable with signature `fn(Request,Handler):Response`.

It is used for assertion of token presence and custom authorization by `AuthWizard` / `AuthFactory`.

It can be used for convenient inline middleware implementation:
```php
$app->add(new GenericMiddleware(function(Request $request, Handler $next): Response {
    $request = $request->withAttribute('foo', 42);
    $response = $next->handle($request);
    return $response->withHeader('foo', 'bar');
}));
```


### `AuthWizard`, `AuthFactory`

[`AuthWizard`] is a friction reducer that helps quickly instantiate the middleware with sensible defaults.\
[`AuthFactory`] is a configurable factory with sensible defaults provided for convenience.\
`AuthWizard` internally instantiates `AuthFactory` and acts as a static proxy for the factory.

Use `AuthFactory::decodeTokens` to create token-decoding middleware.\
Use `AuthFactory::assertTokens` to create middleware that asserts the presence of a decoded token.\
Use `AuthFactory::inspectTokens` to create middleware with custom authorization rules against the token.


### `TokenManipulators`

The [`TokenManipulators`] static class provides various request/response manipulators
that can be used for token handling.\
They are used as components of the middleware.


### `FirebaseJwtDecoder`

The [`FirebaseJwtDecoder`] class serves as the default implementation for JWT token decoding.\
It is used as a _decoder_ for the `TokenMiddleware`.


### Logger

The `TokenMiddleware` accepts a PSR-3 `Logger` instance for debug purposes.


### Tips

Multiple token-decoding and token-inspecting middleware can be stacked too!

Token decoding will usually be applied to the app-level middleware (every route),
but the assertions can be _composed_ and applied to groups or individual routes as needed.


## Testing

Run unit tests using the following command:

`$` `composer test`


## Contributing

Ideas, feature requests and other contribution is welcome.
Please send a PR or create an issue.


### Security Issues

If you happen to find a security problem,
create an issue without disclosing any relevant details,
we'll get in touch and discuss the details privately.



[`TokenMiddleware`]:      src/TokenMiddleware.php
[`PredicateMiddleware`]:  src/PredicateMiddleware.php
[`TokenManipulators`]:    src/TokenManipulators.php
[`FirebaseJwtDecoder`]:   src/FirebaseJwtDecoder.php
[`AuthWizard`]:           src/Factory/AuthWizard.php
[`AuthFactory`]:          src/Factory/AuthFactory.php

