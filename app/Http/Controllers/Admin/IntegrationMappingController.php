<?php

namespace App\Http\Controllers\Admin;

use App\Models\IntegrationMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IntegrationMappingController
{
    /**
     * Fetch data from an external API and return flattened field paths.
     */
    public function fetchApiData(Request $request)
    {
        $request->validate([
            'api_url' => 'required|url',
            'api_method' => 'required|in:GET,POST',
        ]);

        try {
            $method = strtoupper($request->api_method);
            
            if ($method === 'POST') {
                $response = Http::timeout(10)->post($request->api_url);
            } else {
                $response = Http::timeout(10)->get($request->api_url);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data from API',
                ], 400);
            }

            $data = $response->json();
            
            // If the response has a 'data' key with array of items, use first item
            if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                $sampleData = is_array($data['data'][0]) ? $data['data'][0] : $data['data'];
            } else {
                $sampleData = $data;
            }

            // Flatten the array to get all possible field paths
            $fields = IntegrationMapping::flattenArray($sampleData);

            return response()->json([
                'success' => true,
                'fields' => $fields,
                'sample_data' => $sampleData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get fillable fields for a given model.
     */
    public function getModelFields(Request $request)
    {
        $request->validate([
            'model' => 'required|string|in:User,Teacher',
        ]);

        $fields = IntegrationMapping::getModelFillableFields($request->model);

        return response()->json([
            'success' => true,
            'fields' => $fields,
        ]);
    }
}
