<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
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

        Role::updateOrCreate(
            ['username' => 'admin', 'role' => 'admin'],
            []
        );

        $this->command->info('✅ کاربر admin با موفقیت ایجاد شد.');
    }
}
