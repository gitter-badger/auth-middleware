<?php

namespace Dakujem\Middleware\Support;

use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

/**
 * SlimWizard
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class SlimWizard
{
    function addJwtAuthentication($appOrGroup, string $secret, ?ResponseFactoryInterface $rf = null){
        $app = new App($responseFactory);
        $app->add($middleware);
        $app->group($pattern, $callable);
    }

}
