<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'fname'    => 'مدیر',
                'lname'    => 'سیستم',
                'kodmeli'  => '0000000000',
                'mobile'   => '09000000000',
                'email'    => 'admin@yazdrud.ir',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // Assign 'support' (developer) and 'admin' roles via Spatie (for api guard)
        $user->assignRole(['support', 'admin']);

        // Create personal access client if it doesn't exist
        // Passport 13 uses grant_types JSON — 'personal_access' grant type identifies personal clients
        $hasClient = \DB::table('oauth_clients')
            ->where('name', 'portal-api')
            ->whereRaw("JSON_CONTAINS(grant_types, '\"personal_access\"')")
            ->exists();

        if (!$hasClient) {
            \DB::table('oauth_clients')->insert([
                'id'           => \Str::uuid()->toString(),
                'name'         => 'portal-api',
                'secret'       => \Str::random(40),
                'provider'     => 'users',
                'redirect_uris' => '[]',
                'grant_types'  => '["personal_access"]',
                'revoked'      => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->command->info('✅ Passport personal access client "portal-api" created.');
        }

        $this->command->info('✅ کاربر admin با موفقیت ایجاد شد و نقش‌های support و admin به او اختصاص یافت.');
    }
}
