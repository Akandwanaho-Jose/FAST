<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\Logger;

/**
 * Sends transactional email via Brevo's REST API. Deliberately not a
 * library dependency - the codebase has none, and Brevo's API is a single
 * JSON POST, which PHP's built-in curl extension handles directly.
 *
 * Never throws: a delivery failure must never surface to the caller in a
 * way that could leak whether an email address exists in the system (see
 * PasswordResetService, which always shows the same response regardless of
 * whether sending actually succeeded). Failures are logged instead.
 */
final class Mailer
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly Logger $logger
    ) {
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        if ($this->apiKey === '' || $this->fromAddress === '') {
            $this->logger->error('Mailer not configured', ['to' => $toEmail]);

            return false;
        }

        $payload = json_encode(
            [
                'sender' => ['name' => $this->fromName, 'email' => $this->fromAddress],
                'to' => [['email' => $toEmail, 'name' => $toName]],
                'subject' => $subject,
                'htmlContent' => $htmlBody,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($payload === false) {
            $this->logger->error('Mailer payload could not be encoded', ['to' => $toEmail]);

            return false;
        }

        $handle = curl_init(self::ENDPOINT);

        if ($handle === false) {
            $this->logger->error('Mailer could not initialise curl', ['to' => $toEmail]);

            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $status < 200 || $status >= 300) {
            $this->logger->error('Mailer send failed', [
                'to' => $toEmail,
                'status' => $status,
                'curl_error' => $error,
                'response' => is_string($response) ? mb_substr($response, 0, 500) : null,
            ]);

            return false;
        }

        return true;
    }
}
