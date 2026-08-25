<?php

declare(strict_types=1);

namespace Funnelion\Http;

use Funnelion\Exception\NetworkException;
use Funnelion\Exception\TimeoutException;

/**
 * Default HTTP transport using ext-curl. Zero runtime dependencies
 * beyond the cURL extension that every typical PHP install carries.
 *
 * Honours the configured timeout strictly via CURLOPT_TIMEOUT_MS so
 * a slow Funnelion API never blocks the customer's page render past
 * the budget the SDK consumer set.
 */
final class CurlTransport implements Transport
{
    public function send(
        string $method,
        string $uri,
        array $headers,
        string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        $ch = curl_init();
        if ($ch === false) {
            throw new NetworkException('Failed to initialise curl.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $timeoutMs = (int) max(1, round($timeoutSeconds * 1000));

        curl_setopt_array($ch, [
            CURLOPT_URL => $uri,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEOUTED) {
            throw new TimeoutException(
                "Funnelion request timed out after {$timeoutMs}ms.",
                statusCode: null,
            );
        }

        if ($errno !== 0 || $responseBody === false) {
            throw new NetworkException(
                'Funnelion request failed: '.($error !== '' ? $error : 'unknown curl error'),
            );
        }

        return new HttpResponse(
            statusCode: $statusCode,
            body: is_string($responseBody) ? $responseBody : '',
        );
    }
}
