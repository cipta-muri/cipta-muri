<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Filament\Facades\Filament;
echo Filament::getPanel('admin')?->getAuthGuard();
