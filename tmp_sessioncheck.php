<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// first get session
$req1 = Illuminate\Http\Request::create('/_playwright/session?email=testing@ciptamuri.com','GET');
$res1 = $kernel->handle($req1);
$data = json_decode($res1->getContent(), true);
$kernel->terminate($req1, $res1);
$cookie = $data['name'].'='.$data['id'];
// now hit /admin with cookie
$req2 = Illuminate\Http\Request::create('/admin','GET',[],[$data['name']=>$data['id']]);
$res2 = $kernel->handle($req2);
$status = $res2->getStatusCode();
echo "status: $status\n";
echo substr($res2->getContent(),0,200);
$kernel->terminate($req2, $res2);
