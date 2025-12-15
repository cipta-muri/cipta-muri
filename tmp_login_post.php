<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
// simulate login post
$request=Request::create('/admin/login','POST',[
  'email'=>'testing@ciptamuri.com',
  'password'=>'password',
  '_token'=>csrf_token(),
]);
$response=$kernel->handle($request);
echo "status: {$response->getStatusCode()}\n";
echo substr($response->getContent(),0,200);
