<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;use Illuminate\Support\Facades\Hash;
$u = User::where('email','testing@ciptamuri.com')->first();
var_dump(Hash::check('password',$u->password));
