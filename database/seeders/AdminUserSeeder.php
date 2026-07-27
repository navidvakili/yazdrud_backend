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

        // Assign 'admin' role via Spatie (for api guard)
        $user->assignRole('admin');

        $this->command->info('✅ کاربر admin با موفقیت ایجاد شد و نقش admin به او اختصاص یافت.');
    }
}
