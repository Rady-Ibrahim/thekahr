<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected string $appId;
    protected string $restApiKey;

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id', '');
        $this->restApiKey = config('services.onesignal.rest_api_key', '');
    }

    public function isEnabled(): bool
    {
        return !empty($this->appId) && !empty($this->restApiKey);
    }

    /**
     * Send a push notification to specific OneSignal Player IDs (Subscription IDs).
     *
     * @param string[] $playerIds OneSignal Player IDs (Subscription IDs) of registered devices.
     * @param string $title Notification title.
     * @param string $message Notification body.
     * @param array<string,mixed> $data Extra payload delivered to the device.
     */
    public function sendNotification(array $playerIds, string $title, string $message, array $data = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $this->appId,
                'include_player_ids' => array_map('strval', $playerIds),
                'headings' => ['en' => $title, 'ar' => $title],
                'contents' => ['en' => $message, 'ar' => $message],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('OneSignal notification failed', [
                'player_ids' => $playerIds,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
