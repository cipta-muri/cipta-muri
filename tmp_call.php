<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/_playwright/session?email=testing@ciptamuri.com', 'GET');
$response = $kernel->handle($request);
header('Content-Type: text/plain');
echo "Status: " . $response->getStatusCode() . "\n";
echo $response->getContent();
$kernel->terminate($request, $response);
