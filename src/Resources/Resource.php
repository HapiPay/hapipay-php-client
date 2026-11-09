<?php

namespace HapiPay\Client\Resources;

use HapiPay\Client\Transport;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly Transport $transport) {}

    /** Segment d'adresse : une référence peut contenir des caractères à échapper. */
    protected static function seg(string $value): string
    {
        return rawurlencode($value);
    }
}
