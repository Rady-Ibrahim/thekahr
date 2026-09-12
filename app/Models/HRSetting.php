<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HRSetting extends Model
{
    protected $table = 'hr_settings';

    protected $fillable = [
        'key', 'value',
    ];

    private const CACHE_PREFIX = 'hr_setting.';

    /**
     * The default early-exit deduction switch. When false, no employee receives
     * an early-exit discount regardless of shift rules or per-employee overrides.
     */
    public const EARLY_EXIT_DEDUCTION_ENABLED = 'early_exit_deduction_enabled';

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX . $key, fn () => self::query()->where('key', $key)->value('value'));

        if ($value === null) {
            return $default;
        }

        // Store booleans/int floats as their literal string; decode them back.
        return match (mb_strtolower((string) $value)) {
            'true', '1' => true,
            'false', '0' => false,
            default => $value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $normalized = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        self::query()->updateOrCreate(['key' => $key], ['value' => $normalized]);

        Cache::forget(self::CACHE_PREFIX . $key);
    }
}