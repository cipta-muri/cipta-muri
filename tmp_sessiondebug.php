<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
$request = Request::create('/_playwright/login?email=testing@ciptamuri.com','GET');
$response = $kernel->handle($request);
$session = $request->session();
var_dump($session->all());
