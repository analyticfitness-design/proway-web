<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\WhatsApp;

/**
 * WhatsApp Business Cloud API service.
 *
 * Sends messages via Meta Graph API v21.0.
 * Requires WA_PHONE_NUMBER_ID and WA_ACCESS_TOKEN env vars.
 *
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages
 */
class WhatsAppService
{
    private const API_BASE = 'https://graph.facebook.com/v21.0';

    private string $phoneNumberId;
    private string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = defined('WA_PHONE_NUMBER_ID') ? WA_PHONE_NUMBER_ID : '';
        $this->accessToken   = defined('WA_ACCESS_TOKEN')    ? WA_ACCESS_TOKEN    : '';
    }

    /**
     * Whether the service is configured (has credentials).
     */
    public function isConfigured(): bool
    {
        return $this->phoneNumberId !== '' && $this->accessToken !== '';
    }

    /**
     * Send a template-based message.
     *
     * @param string $phone         Recipient phone with country code (e.g. +573193198646)
     * @param string $templateName  Pre-approved WhatsApp template name
     * @param array  $parameters    Body parameters for the template
     * @return bool True if the API returned a successful response
     */
    public function sendTemplate(string $phone, string $templateName, array $parameters = []): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $phone = $this->normalizePhone($phone);

        $components = [];
        if (!empty($parameters)) {
            $bodyParams = array_map(
                fn(string $value) => ['type' => 'text', 'text' => $value],
                array_values($parameters)
            );
            $components[] = [
                'type'       => 'body',
                'parameters' => $bodyParams,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => 'es'],
                'components' => $components,
            ],
        ];

        return $this->sendRequest($payload);
    }

    /**
     * Send a simple text message.
     *
     * @param string $phone Recipient phone with country code
     * @param string $text  Message text
     * @return bool True if the API returned a successful response
     */
    public function sendText(string $phone, string $text): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $phone = $this->normalizePhone($phone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => [
                'preview_url' => true,
                'body'        => $text,
            ],
        ];

        return $this->sendRequest($payload);
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * Execute the cURL POST to WhatsApp Cloud API.
     */
    private function sendRequest(array $payload): bool
    {
        $url = self::API_BASE . '/' . $this->phoneNumberId . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Log every attempt for debugging
        $logData = [
            'to'        => $payload['to'] ?? '?',
            'type'      => $payload['type'] ?? '?',
            'http_code' => $httpCode,
            'success'   => $httpCode >= 200 && $httpCode < 300,
        ];

        if ($response === false) {
            $logData['curl_error'] = $curlErr;
            error_log('[WhatsApp] FAILED — ' . json_encode($logData));
            return false;
        }

        $body = json_decode((string) $response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $logData['message_id'] = $body['messages'][0]['id'] ?? null;
            error_log('[WhatsApp] OK — ' . json_encode($logData));
            return true;
        }

        $logData['error'] = $body['error']['message'] ?? substr((string) $response, 0, 200);
        error_log('[WhatsApp] ERROR — ' . json_encode($logData));
        return false;
    }

    /**
     * Strip non-numeric characters except leading +.
     * WhatsApp Cloud API expects digits only (no + prefix).
     */
    private function normalizePhone(string $phone): string
    {
        return ltrim(preg_replace('/[^0-9]/', '', $phone), '0');
    }
}
