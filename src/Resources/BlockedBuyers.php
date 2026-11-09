<?php

namespace HapiPay\Client\Resources;

/** Numéros d'acheteurs suspendus sur votre plateforme après trop de litiges. */
final class BlockedBuyers extends Resource
{
    /** @return list<array<string, mixed>> */
    public function lister(): array
    {
        return $this->transport->request('GET', 'acheteurs-suspendus');
    }

    /** @return array<string, mixed> */
    public function retablir(string $telephone): array
    {
        return $this->transport->request('DELETE', 'acheteurs-suspendus/'.self::seg($telephone));
    }
}
