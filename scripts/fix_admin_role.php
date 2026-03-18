<?php
// Fix: Remove the incorrectly-added student role from the admin account

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

$email = 'menorjoseph767@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "User not found: {$email}\n";
    exit(1);
}

echo "User ID: {$user->id}\n";
echo "User Name: {$user->name}\n";

// Show current roles
$roles = DB::table('portal_user_roles')->where('user_id', $user->id)->get();
echo "Current roles:\n";
foreach ($roles as $role) {
    echo "  - {$role->role} (is_active: {$role->is_active})\n";
}

// Delete the student role row (keep admin)
$deleted = DB::table('portal_user_roles')
    ->where('user_id', $user->id)
    ->where('role', 'student')
    ->delete();

echo "\nDeleted student role rows: {$deleted}\n";

// Show remaining roles
$remaining = DB::table('portal_user_roles')->where('user_id', $user->id)->get();
echo "Remaining roles:\n";
foreach ($remaining as $role) {
    echo "  - {$role->role} (is_active: {$role->is_active})\n";
}

echo "\nDone! Admin role restored.\n";
