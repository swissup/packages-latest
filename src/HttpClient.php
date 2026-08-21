<?php

declare(strict_types=1);

namespace Swissup\PackagesLatest;

final class HttpClient
{
    private const USER_AGENT = 'swissup-latest-packages/1.0.0 (+https://swissup.github.io/packages-latest/; mailto:support@swissuplabs.com)';

    private const MAX_CONCURRENCY = 20;
    private const MAX_CONNECTIONS = 6;
    private const RETRIES = 2;

    private \CurlMultiHandle $multi;
    private int $requests = 0;
    private int $peakInFlight = 0;
    private array $protocols = [];

    public function __construct(private readonly Logger $log)
    {
        $this->multi = curl_multi_init();
        curl_multi_setopt($this->multi, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
        curl_multi_setopt($this->multi, CURLMOPT_MAX_HOST_CONNECTIONS, self::MAX_CONNECTIONS);
    }

    public function __destruct()
    {
        curl_multi_close($this->multi);
    }

    public function get(string $url): string
    {
        return $this->getMany(['body' => $url])['body'];
    }

    // Handles are added as slots free up: adding all of them up front makes the remote
    // silently return empty bodies for much of the batch.
    public function getMany(array $urls): array
    {
        $pending = $urls;
        $bodies = [];
        $attempts = [];
        $inFlight = [];

        while ($pending !== [] || $inFlight !== []) {
            while (count($inFlight) < self::MAX_CONCURRENCY && $pending !== []) {
                $key = array_key_first($pending);
                $url = $pending[$key];
                unset($pending[$key]);

                $handle = $this->createHandle($url);
                curl_multi_add_handle($this->multi, $handle);
                $inFlight[spl_object_id($handle)] = [$key, $url, $handle];
                $this->requests++;
            }

            $this->peakInFlight = max($this->peakInFlight, count($inFlight));

            do {
                $status = curl_multi_exec($this->multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            if ($running > 0 && curl_multi_select($this->multi, 0.1) === -1) {
                usleep(1000);
            }

            $backoff = 0;
            while (($message = curl_multi_info_read($this->multi)) !== false) {
                $handle = $message['handle'];
                [$key, $url] = $inFlight[spl_object_id($handle)];
                unset($inFlight[spl_object_id($handle)]);

                $body = (string) curl_multi_getcontent($handle);
                $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $failure = $message['result'] !== CURLE_OK
                    ? curl_error($handle)
                    : ($code !== 200 ? 'HTTP ' . $code : ($body === '' ? 'empty response body' : null));

                if ($failure === null) {
                    $this->protocols[(int) curl_getinfo($handle, CURLINFO_HTTP_VERSION)] = true;
                }

                curl_multi_remove_handle($this->multi, $handle);
                curl_close($handle);

                if ($failure === null) {
                    $bodies[$key] = $body;
                    continue;
                }

                $attempt = ($attempts[$key] ?? 0) + 1;
                $attempts[$key] = $attempt;

                if ($attempt > self::RETRIES) {
                    throw new \RuntimeException(sprintf('%s: %s (after %d attempts)', $url, $failure, $attempt));
                }

                $this->log->warn(sprintf('%s: %s, retrying (%d/%d)', $url, $failure, $attempt, self::RETRIES));
                $pending[$key] = $url;
                $backoff = max($backoff, 200000 * $attempt);
            }

            if ($backoff > 0 && $inFlight === []) {
                usleep($backoff);
            }
        }

        return $bodies;
    }

    public function stats(): array
    {
        $labels = array_map(
            static fn (int $version): string => match ($version) {
                CURL_HTTP_VERSION_1_0 => 'HTTP/1.0',
                CURL_HTTP_VERSION_1_1 => 'HTTP/1.1',
                CURL_HTTP_VERSION_2_0 => 'HTTP/2',
                CURL_HTTP_VERSION_3 => 'HTTP/3',
                default => 'HTTP/?' . $version,
            },
            array_keys($this->protocols)
        );
        sort($labels);

        return [
            'requests' => $this->requests,
            'peak_in_flight' => $this->peakInFlight,
            'protocols' => implode(', ', $labels),
        ];
    }

    private function createHandle(string $url): \CurlHandle
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);

        return $handle;
    }
}
