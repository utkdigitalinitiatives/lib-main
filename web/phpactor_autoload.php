<?php

declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = DrupalKernel::createFromRequest(
    Request::create('http://localhost/'),
    $autoloader,
    'prod',
    FALSE,
    __DIR__,
    'sites/default',
);
$kernel->boot();

return $autoloader;
