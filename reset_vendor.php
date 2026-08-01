<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use App\Models\Vendor;

// Require Composer autoload
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';

// Make the application kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Boot the app (important for Eloquent)
$kernel->bootstrap();

// --- Your logic ---
$v = Vendor::find(18);  // now this works
$temp = 'NewTemp123';
$v->password = Hash::make($temp);
$v->temp_password_encrypted = Crypt::encryptString($temp);
$v->save();

echo "Vendor password reset successfully!\n";
