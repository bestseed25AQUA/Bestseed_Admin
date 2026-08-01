<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table = 'app_configs';


    protected $fillable = [
        'config_key',
        'config_value',
        'config_group',
        'description',
    ];

    /**
     * Get a config value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('config_key', $key)->first();
        return $config ? $config->config_value : $default;
    }

    /**
     * Set a config value by key
     */
    public static function setValue(string $key, $value, string $group = 'general', string $description = null)
    {
        return self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $value,
                'config_group' => $group,
                'description' => $description,
            ]
        );
    }

    /**
     * Get all configs by group
     */
    public static function getByGroup(string $group)
    {
        return self::where('config_group', $group)->get();
    }

    /**
     * Get all configs as key-value array
     */
    public static function getAllAsArray()
    {
        return self::pluck('config_value', 'config_key')->toArray();
    }

    /**
     * Get configs by group as key-value array
     */
    public static function getGroupAsArray(string $group)
    {
        return self::where('config_group', $group)
            ->pluck('config_value', 'config_key')
            ->toArray();
    }
}
