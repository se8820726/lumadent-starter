<?php

declare(strict_types=1);

namespace Lumadent\Deployer;

require_once __DIR__.'/SharedHostDeployer.php';

/** @return array{status:int,body:array{deployment:array{status:string}}} */
function runDeployment(array $request): array
{
    return (new SharedHostDeployer(dirname(__DIR__)))->handle($request);
}
