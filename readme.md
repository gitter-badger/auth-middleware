# PSR-15 Auth Middleware

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/auth-middleware)
![PHP 8 ready](https://img.shields.io/static/v1?label=php%208&message=ready%20%F0%9F%91%8D&color=green)
[![Build Status](https://travis-ci.org/dakujem/auth-middleware.svg?branch=main)](https://travis-ci.org/dakujem/auth-middleware)
[![Coverage Status](https://coveralls.io/repos/github/dakujem/auth-middleware/badge.svg?branch=main)](https://coveralls.io/github/dakujem/auth-middleware?branch=main)
[![Join the chat at https://gitter.im/dakujem/auth-middleware](https://badges.gitter.im/dakujem/auth-middleware.svg)](https://gitter.im/dakujem/auth-middleware)

Modern and highly flexible PSR-15 authentication and authorization middleware.

> 💿 `composer require dakujem/auth-middleware`


## Default Usage

To use this package, you create **two middleware layers**:
- a **token-decoding** middleware that decodes encoded JWT present in the request and verifies its authenticity
- and a **middleware that authorizes** the request by asserting the presence of the decoded token

Use `Dakujem\Middleware\AuthWizard` for convenience:
```php
/* @var Slim\App $app */
$app->add(AuthWizard::assertTokens($app->getResponseFactory()));
$app->add(AuthWizard::decodeTokens('a-secret-api-key-never-to-commit'));
```

The pair of middleware (MW) will look for a [JWT](https://jwt.io/introduction/)
in the `Authorization` header or `token` cookie.\
Then it will decode the JWT and inject the decoded payload to the `token` request attribute,
accessible to the application.\
If the token is not present or is not valid, the execution pipeline will be terminated
by the assertion middleware
and a `401 Unauthorized` response will be returned.

The token can be accessed via the request attribute:
```php
/* @var Request $request */
$decodedToken = $request->getAttribute('token');
```

You can choose to apply the assertion to selected routes only instead of every route:
```php
$mwFactory = AuthWizard::factory('a-secret-api-key-never-to-commit', $app->getResponseFactory());

// Decode the token for all routes,
$app->add($mwFactory->decodeTokens());

// but only apply the assertion to selected ones.
$app->group('/foo', ...)->add($mwFactory->assertTokens());
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

💡\
For highly flexible options to instantiate the middleware,
read the ["Compose Your Own Middleware"](#compose-your-own-middleware) chapter below.

>
> The examples above use [Slim PHP](https://www.slimframework.com) framework,
> but the same usage applies to any [PSR-15](https://www.php-fig.org/psr/psr-15/)
> compatible middleware dispatcher.
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
Using `AuthWizard::inspectTokens`, the pipeline can be terminated on any conditions, involving the token or not.\
Custom error messages or data can be passed to the Response.

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
The cast can either be done in the decoder or in a separate middleware.


## Compose Your Own Middleware

In the examples above, we are using the [`AuthWizard`] helper which provides sensible defaults.\
However, it is possible and encouraged to build your own middleware using the components provided by this package.

You have the flexibility to fine-tune the middleware for any use case.

>
> I'm using aliased names instead of full interface names in this documentation for brevity.
>
> Here are the full interface names:
>
> | Alias | Full interface name |
> |:------|:--------------------|
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

Usage tips 💡:
- The decoder can be swapped in order to use **OAuth tokens** or a different JWT implementation.
- Exceptions may be caught and processed by the _injector_ by wrapping the provider callable in a try-catch block
  `try { $token = $provider(); } catch (RuntimeException $e) { ... `
- The decoder may return any object, this is the place to cast the raw payload into your object of choice.
  Alternatively, a separate middleware can be used for that purpose.


### `AuthWizard`, `AuthFactory`

[`AuthWizard`] is a friction reducer that helps quickly instantiate token-decoding and assertion middleware with sensible defaults.\
[`AuthFactory`] is a configurable factory with sensible defaults provided for convenience.\
`AuthWizard` internally instantiates `AuthFactory` and acts as a static facade for the factory.

Use `AuthFactory::decodeTokens` to create token-decoding middleware.\
Use `AuthFactory::assertTokens` to create middleware that asserts the presence of a decoded token.\
Use `AuthFactory::inspectTokens` to create middleware with custom authorization rules against the token.


### `GenericMiddleware`

The [`GenericMiddleware`] is used for assertion of token presence and custom authorization by `AuthWizard` / `AuthFactory`.

It can also be used for convenient inline middleware implementation:
```php
$app->add(new GenericMiddleware(function(Request $request, Handler $next): Response {
    $request = $request->withAttribute('foo', 42);
    $response = $next->handle($request);
    return $response->withHeader('foo', 'bar');
}));
```


### `TokenManipulators`

The [`TokenManipulators`] static class provides various request/response manipulators
that can be used for token handling.\
They are used as components of the middleware.


### `FirebaseJwtDecoder`

The [`FirebaseJwtDecoder`] class serves as the default implementation for JWT token decoding.\
It is used as a _decoder_ for the `TokenMiddleware`.\
You can swap it for a different implementation.

You need to install [Firebase JWT](https://github.com/firebase/php-jwt) package in order to use this decoder.\
`composer require firebase/php-jwt:"^5.0"`


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
[`GenericMiddleware`]:    https://github.com/dakujem/generic-middleware#readme
[`TokenManipulators`]:    src/TokenManipulators.php
[`FirebaseJwtDecoder`]:   src/FirebaseJwtDecoder.php
[`AuthWizard`]:           src/Factory/AuthWizard.php
[`AuthFactory`]:          src/Factory/AuthFactory.php

