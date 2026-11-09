<?php

namespace HapiPay\Client\Exceptions;

use RuntimeException;

/**
 * Réponse d'erreur de l'API HapiPay. Le message est celui de l'API, en
 * français, prêt à être montré ou journalisé.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body  corps JSON de la réponse
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $body = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
