<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PublicQuoteUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => 'publicquote@gmail.com']);

        $user->forceFill([
            'name' => 'Tài khoản Báo Giá',
            'password' => 'stlqoute',
            'email_verified_at' => now(),
            'is_admin' => false,
        ])->save();
    }
}
