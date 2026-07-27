<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Current state ===\n";
$roles = Role::where('guard_name', 'api')->withCount('users')->withCount('permissions')->get();
foreach ($roles as $r) {
    echo "  id={$r->id} name={$r->name} users={$r->users_count} perms={$r->permissions_count}\n";
}

echo "\n=== Recreating deleted roles ===\n";
$editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
$user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
echo "editor id={$editor->id}, user id={$user->id}\n";

// Assign roles
$adminUser = User::where('username', 'admin')->first();
if ($adminUser) {
    if (!$adminUser->hasRole('editor', 'api')) {
        $adminUser->assignRole('editor', 'api');
        echo "Assigned editor to admin user\n";
    } else {
        echo "Admin already has editor role\n";
    }
}

// Sync permissions
$allPerms = Permission::where('guard_name', 'api')->pluck('name')->toArray();
$editor->syncPermissions($allPerms);
echo "Synced ALL permissions to editor role (" . count($allPerms) . " perms)\n";

$viewPerms = Permission::where('guard_name', 'api')
    ->where('name', 'like', '%.view')
    ->pluck('name')
    ->toArray();
$user->syncPermissions($viewPerms);
echo "Synced view permissions to user role (" . count($viewPerms) . " perms)\n";

// Final state
echo "\n=== Final state ===\n";
$roles = Role::where('guard_name', 'api')->withCount('users')->withCount('permissions')->get();
foreach ($roles as $r) {
    echo "  id={$r->id} name={$r->name} users={$r->users_count} perms={$r->permissions_count}\n";
}

// Check admin user roles
$adminUser = User::where('username', 'admin')->first();
if ($adminUser) {
    $adminRoles = $adminUser->getRoleNames('api');
    echo "\nAdmin user roles: " . $adminRoles->implode(', ') . "\n";
}
