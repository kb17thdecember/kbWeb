<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'kbnguyen0311@gmail.com']);

        $admin->forceFill([
            'name' => 'Nguyễn Tuấn Kiệt',
            'password' => 'kbnguyen0311@',
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();
    }
}
