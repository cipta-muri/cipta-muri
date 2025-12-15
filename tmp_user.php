<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
$u = User::where('email','testing@ciptamuri.com')->first();
if(!$u){echo "no user"; exit;}
echo "id={$u->id}\nnik={$u->nik}\npassword={$u->password}\n";
