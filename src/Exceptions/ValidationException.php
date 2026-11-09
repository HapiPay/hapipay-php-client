<?php

namespace HapiPay\Client\Exceptions;

/**
 * HTTP 422 : champs refusés, ou plafond dépassé. errors() donne les
 * messages par champ quand l'API les précise.
 */
class ValidationException extends ApiException
{
    /** @return array<string, list<string>> champ => messages */
    public function errors(): array
    {
        return (array) ($this->body['errors'] ?? []);
    }
}
