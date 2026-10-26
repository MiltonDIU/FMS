<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use Concerns\RecordsActivity {
        getActivitylogOptions as defaultActivitylogOptions;
        shouldLogEvent as defaultShouldLogEvent;
    }

    /** set() rewrites the group to "system" on every save; it says nothing. */
    protected array $activityLogIgnore = ['group'];

    /**
     * A credential's change is recorded, never its value: the log is written
     * to be read, and the HR API secret and password sign the system in.
     */
    public function getActivitylogOptions(): \Spatie\Activitylog\Support\LogOptions
    {
        $options = $this->defaultActivitylogOptions();

        if (preg_match('/secret|password|token/i', (string) $this->key)) {
            $options->logExcept(['value', 'group', 'created_at', 'updated_at', 'sort_order'])->logEmptyChanges();
        }

        return $options;
    }

    /** Progress counters the import and export screens poll, not settings anyone chose. */
    protected function shouldLogEvent(string $eventName): bool
    {
        return ! str_ends_with((string) $this->key, '_progress') && $this->defaultShouldLogEvent($eventName);
    }

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value): bool
    {
        $attributes = ['value' => $value, 'group' => 'system'];

        // Convert value to string for storage and detect type
        if (is_bool($value)) {
            $attributes['value'] = $value ? 'true' : 'false';
            $attributes['type'] = 'boolean';
        } elseif (is_array($value)) {
            $attributes['value'] = json_encode($value);
            $attributes['type'] = 'json';
        } elseif (is_int($value)) {
            $attributes['type'] = 'integer';
        }

        $setting = static::updateOrCreate(
             ['key' => $key],
             $attributes
        );

        // Clear cache
        Cache::forget("setting.{$key}");

        return true;
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => static::get($setting->key)])
            ->toArray();
    }
}
