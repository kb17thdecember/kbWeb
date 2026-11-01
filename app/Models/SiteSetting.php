<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array{
     *     company_name: string,
     *     company_tagline: string,
     *     announcement_text: string,
     *     footer_description: string,
     *     copyright_text: string,
     *     hotline: string,
     *     zalo_phone: string,
     *     contact_email: string,
     *     address: string,
     *     working_hours: string,
     *     facebook_url: string,
     *     linkedin_url: string,
     *     zalo_url: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'company_name' => 'HVAC Mart',
            'company_tagline' => 'HVAC & ENGINEERING SUPPLY',
            'announcement_text' => 'Giải pháp vật tư & thiết bị · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện',
            'footer_description' => 'Đầu mối cung ứng thiết bị và vật tư kỹ thuật cho các công trình dân dụng, thương mại và công nghiệp.',
            'copyright_text' => '© 2026 HVAC Mart. Bảo lưu mọi quyền.',
            'hotline' => '0979.807.234',
            'zalo_phone' => '0979807234',
            'contact_email' => 'kbnguyen0311@gmail.com',
            'address' => 'Hà Nội, Việt Nam',
            'working_hours' => 'Thứ 2 – Thứ 7 · 08:00–17:30',
            'facebook_url' => '/contacts',
            'linkedin_url' => '/contacts',
            'zalo_url' => 'https://zalo.me/0979807234',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allSettings(): array
    {
        $defaults = self::defaults();

        try {
            $stored = self::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultValue) {
            if (array_key_exists($key, $stored) && $stored[$key] !== null && trim((string) $stored[$key]) !== '') {
                $defaults[$key] = (string) $stored[$key];
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function updateSettings(array $values): void
    {
        $allowedKeys = array_keys(self::defaults());

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }

            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value !== null ? trim($value) : null],
            );
        }
    }
}
