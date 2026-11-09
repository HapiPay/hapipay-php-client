<?php

namespace HapiPay\Client;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use HapiPay\Client\Exceptions\ApiException;
use HapiPay\Client\Exceptions\AuthenticationException;
use HapiPay\Client\Exceptions\ConflictException;
use HapiPay\Client\Exceptions\ConnectionException;
use HapiPay\Client\Exceptions\ForbiddenException;
use HapiPay\Client\Exceptions\NotFoundException;
use HapiPay\Client\Exceptions\PaymentRequiredException;
use HapiPay\Client\Exceptions\RateLimitException;
use HapiPay\Client\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Appels HTTP vers l'API : clé d'API, JSON, erreurs traduites en
 * exceptions. Renvoie le contenu de « data ».
 *
 * @internal
 */
final class Transport
{
    public const USER_AGENT = 'hapipay-php-client/1.0';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        float $timeout = 30.0,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Guzzle(['timeout' => $timeout, 'connect_timeout' => 10]);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $query
     * @param  list<array<string, mixed>>|null  $multipart
     * @return array<mixed>
     */
    public function request(string $method, string $path, ?array $json = null, array $query = [], ?array $multipart = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
            'http_errors' => false,
        ];

        if ($json !== null) {
            $options['json'] = array_filter($json, fn ($value) => $value !== null);
        }

        if ($query !== []) {
            $options['query'] = array_filter($query, fn ($value) => $value !== null);
        }

        if ($multipart !== null) {
            $options['multipart'] = $multipart;
        }

        try {
            $response = $this->http->request($method, rtrim($this->baseUrl, '/').'/'.ltrim($path, '/'), $options);
        } catch (ConnectException $e) {
            throw new ConnectionException('HapiPay est injoignable : '.$e->getMessage(), 0, [], $e);
        } catch (GuzzleException $e) {
            throw new ConnectionException('Appel à HapiPay impossible : '.$e->getMessage(), 0, [], $e);
        }

        return $this->decode($response);
    }

    /** @return array<mixed> */
    private function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];

        if ($status >= 200 && $status < 300) {
            return is_array($body['data'] ?? null) ? $body['data'] : $body;
        }

        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : "Réponse HTTP {$status} de HapiPay.";

        throw match (true) {
            $status === 401 => new AuthenticationException($message, $status, $body),
            $status === 402 => new PaymentRequiredException($message, $status, $body),
            $status === 403 => new ForbiddenException($message, $status, $body),
            $status === 404 => new NotFoundException($message, $status, $body),
            $status === 409 => new ConflictException($message, $status, $body),
            $status === 422 => new ValidationException($message, $status, $body),
            $status === 429 => new RateLimitException($message, $status, $body),
            default => new ApiException($message, $status, $body),
        };
    }
}
