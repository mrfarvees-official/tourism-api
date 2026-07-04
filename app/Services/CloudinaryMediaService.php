<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudinaryMediaService
{
    private function cloudName(): string
    {
        return (string) config('services.cloudinary.cloud_name');
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('services.cloudinary.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Cloudinary API key is not configured.');
        }

        return $apiKey;
    }

    private function apiSecret(): string
    {
        $apiSecret = (string) config('services.cloudinary.api_secret');
        if ($apiSecret === '') {
            throw new RuntimeException('Cloudinary API secret is not configured.');
        }

        return $apiSecret;
    }

    private function folder(): string
    {
        return trim((string) config('services.cloudinary.folder', 'tourism-app'), '/');
    }

    private function baseUrl(): string
    {
        $cloudName = $this->cloudName();
        if ($cloudName === '') {
            throw new RuntimeException('Cloudinary cloud name is not configured.');
        }

        return "https://api.cloudinary.com/v1_1/{$cloudName}";
    }

    /**
     * @param array<string, scalar> $params
     */
    private function signParams(array $params): string
    {
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . (string) $value;
        }

        $query = implode('&', $pairs);
        return sha1($query . $this->apiSecret());
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadImage(UploadedFile $file): array
    {
        $timestamp = now()->timestamp;
        $signingParams = [
            'folder' => $this->folder(),
            'overwrite' => 'true',
            'timestamp' => $timestamp,
            'unique_filename' => 'true',
        ];

        $payload = $signingParams + [
            'api_key' => $this->apiKey(),
            'resource_type' => 'image',
            'signature' => $this->signParams($signingParams),
        ];

        $response = Http::timeout(90)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("{$this->baseUrl()}/image/upload", $payload);

        if (!$response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'Cloudinary upload failed.');
        }

        $data = $response->json();

        if (!is_array($data) || !isset($data['public_id'], $data['secure_url'])) {
            throw new RuntimeException('Cloudinary upload returned an invalid payload.');
        }

        return $data;
    }

    /**
     * @param array<string, scalar> $params
     */
    public function deleteImage(string $publicId): array
    {
        $timestamp = now()->timestamp;
        $signingParams = [
            'invalidate' => 'true',
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $payload = $signingParams + [
            'api_key' => $this->apiKey(),
            'signature' => $this->signParams($signingParams),
        ];

        $response = Http::timeout(60)
            ->asForm()
            ->post("{$this->baseUrl()}/image/destroy", $payload);

        if (!$response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'Cloudinary delete failed.');
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException('Cloudinary delete returned an invalid payload.');
        }

        return $data;
    }
}
