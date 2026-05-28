<?php

namespace App\Support;

use App\Models\SystemSetting;

class SystemSettingHelper
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return SystemSetting::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function setBool(string $key, bool $value): void
    {
        self::set($key, $value ? '1' : '0');
    }
}
