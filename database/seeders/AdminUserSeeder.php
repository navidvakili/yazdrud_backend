<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $this->command->info('✅ کاربر admin با موفقیت ایجاد شد و نقش‌های support و admin به او اختصاص یافت.');
    }
}
