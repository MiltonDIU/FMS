<?php

namespace App\Services;

use App\Models\IntegrationMapping;
use Illuminate\Support\Arr;

class IntegrationService
{
    /**
     * Transform the raw API data based on the mapping configuration.
     *
     * @param array $data The single record from the external API.
     * @param string $slug The slug of the IntegrationMapping to use.
     * @return array The transformed data structure.
     */
    public function transform(array $data, string $slug): array
    {
        $mapping = IntegrationMapping::where('slug', $slug)->first();

        if (!$mapping || empty($mapping->mapping_config)) {
            // Return original if no mapping found
            return $data;
        }

        $transformed = [];

        foreach ($mapping->mapping_config as $config) {
            $sourceField = $config['source_field'] ?? null;
            $targetModel = $config['target_model'] ?? null;
            $targetField = $config['target_field'] ?? null;

            if ($sourceField && $targetField) {
                $value = Arr::get($data, $sourceField);

                // We structure the output by model to make it easy to consume
                // e.g. ['Teacher' => ['employee_id' => 123], 'User' => ['email' => '...']]
                // Or if the frontend expects a flat structure, we can do that too.
                // Given the requirement "kon table a data jabe", knowing the model is useful.
                
                if ($targetModel) {
                    $transformed[$targetModel][$targetField] = $value;
                } else {
                    $transformed[$targetField] = $value;
                }
            }
        }

        return $transformed;
    }
}
