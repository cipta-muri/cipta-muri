<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$result = Illuminate\Support\Facades\Auth::attempt(['email'=>'testing@ciptamuri.com','password'=>'password']);
var_dump($result);
?>
