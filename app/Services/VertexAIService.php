<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VertexAIService
{
    protected string $projectId;
    protected string $region;
    protected string $keyPath;

    public function __construct()
    {
        $this->projectId = env('VERTEX_AI_PROJECT_ID', 'gen-lang-client-0766965418');
        $this->region = env('VERTEX_AI_REGION', 'us-central1');
        
        $keyPath = env('VERTEX_AI_KEY_PATH', 'storage/app/vertex-ai-key.json');
        // Resolve absolute path if it is relative to the project root
        $this->keyPath = (strpos($keyPath, '/') === 0 || strpos($keyPath, '\\') === 0 || preg_match('/^[a-zA-Z]:/', $keyPath))
            ? $keyPath
            : base_path($keyPath);
    }

    /**
     * Get or generate OAuth 2.0 Access Token using the Service Account Credentials.
     * Tokens are cached to optimize sequential/batch execution.
     *
     * @return string
     */
    public function getAccessToken(): string
    {
        return Cache::remember('vertex_ai_access_token', 3000, function () {
            if (!file_exists($this->keyPath)) {
                throw new \RuntimeException("Vertex AI Service Account JSON file not found at: {$this->keyPath}");
            }

            $serviceAccount = json_decode(file_get_contents($this->keyPath), true);
            if (!$serviceAccount || !isset($serviceAccount['private_key']) || !isset($serviceAccount['client_email'])) {
                throw new \RuntimeException("Invalid Service Account JSON format.");
            }

            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            
            $now = time();
            $payload = json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ]);

            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

            $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;
            
            $privateKey = $serviceAccount['private_key'];
            $success = openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
            if (!$success) {
                throw new \RuntimeException("Failed to sign JWT with private key. Ensure openssl extension is enabled.");
            }
            
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            $jwt = $signatureInput . '.' . $base64UrlSignature;

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Failed to get OAuth token from Google: " . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Generate content using Google Cloud Vertex AI (Gemini).
     *
     * @param string $model
     * @param string $prompt
     * @param float $temperature
     * @param string $responseMimeType
     * @return string
     */
    public function generateContent(string $model, string $prompt, float $temperature = 0.0, string $responseMimeType = 'application/json'): array
    {
        $accessToken = $this->getAccessToken();
        
        $endpoint = "https://{$this->region}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->region}/publishers/google/models/{$model}:generateContent";

        $maxRetries = 3;
        $delaySeconds = 4;
        $response = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withToken($accessToken)
                ->timeout(240)
                ->post($endpoint, [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'responseMimeType' => $responseMimeType,
                    ],
                ]);

            if ($response->successful()) {
                break;
            }

            // If rate limited (429) or server error (5xx), sleep and retry
            if (($response->status() === 429 || $response->status() >= 500) && $attempt < $maxRetries) {
                $sleepTime = $delaySeconds * pow(2, $attempt - 1);
                sleep($sleepTime);
                continue;
            }

            // Otherwise, fail immediately (e.g. 400, 401, 403, or max retries reached)
            throw new \RuntimeException("Vertex AI API {$response->status()}: " . $response->body());
        }

        if (!$response || !$response->successful()) {
            throw new \RuntimeException("Vertex AI API call failed after {$maxRetries} attempts.");
        }

        $inputTokens = $response->json('usageMetadata.promptTokenCount', 0);
        $outputTokens = $response->json('usageMetadata.candidatesTokenCount', 0);
        
        // Calculate estimated cost for Gemini Flash models
        $cost = ($inputTokens * 0.000000075) + ($outputTokens * 0.00000030);

        // Write to log file for history
        $logPath = storage_path('logs/vertex_ai_cost.log');
        $logMessage = sprintf(
            "[%s] Model: %s | Input: %d tokens | Output: %d tokens | Est. Cost: $%f\n",
            date('Y-m-d H:i:s'),
            $model,
            $inputTokens,
            $outputTokens,
            $cost
        );
        file_put_contents($logPath, $logMessage, FILE_APPEND);

        return [
            'content' => $response->json('candidates.0.content.parts.0.text', ''),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost
        ];
    }
}
