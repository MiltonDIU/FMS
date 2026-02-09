<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'api_url',
        'api_method',
        'mapping_config',
    ];

    protected $casts = [
        'mapping_config' => 'array',
    ];

    /**
     * Get fillable fields for a given model name.
     */
    public static function getModelFillableFields(string $modelName): array
    {
        $modelClass = "App\\Models\\{$modelName}";
        
        if (!class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        return $model->getFillable();
    }

    /**
     * Flatten a nested array/JSON to dot notation paths.
     */
    public static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : "{$prefix}.{$key}";
            
            if (is_array($value) && !empty($value)) {
                // Check if it's an associative array (not a list)
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $result = array_merge($result, self::flattenArray($value, $newKey));
                } else {
                    // It's a list, just add the key
                    $result[] = $newKey;
                }
            } else {
                $result[] = $newKey;
            }
        }
        
        return $result;
    }
}
