<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/../vendor/nette/tester/src/bootstrap.php';
require_once __DIR__ . '/../src/Factory/AuthFactory.php';

use Dakujem\Middleware\Factory\AuthFactory;
use Dakujem\Middleware\FirebaseJwtDecoder;
use LogicException;
use Tester\Assert;

/**
 * Test the behaviour when the peer lib is not installed.
 *
 * @see FirebaseJwtDecoder
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */

Assert::throws(
    fn() => AuthFactory::defaultDecoderFactory('doesntmatter'),
    LogicException::class,
    'Firebase JWT is not installed. Requires firebase/php-jwt package (`composer require firebase/php-jwt:"^5.0"`).'
);
