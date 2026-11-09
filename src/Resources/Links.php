<?php

namespace HapiPay\Client\Resources;

/** Pages de paiement HapiPay et leur QR code. */
final class Links extends Resource
{
    /**
     * Champs : montant, devise, reference, titre, description, usage_unique,
     * expire_le, url_retour, langue, champs…
     *
     * @param  array<string, mixed>  $lien
     * @return array<string, mixed> dont « url » et « qr »
     */
    public function creer(?string $marchand, array $lien): array
    {
        return $this->transport->request('POST', $marchand === null ? 'liens' : 'marchands/'.self::seg($marchand).'/liens', $lien);
    }

    /** @return array<string, mixed> */
    public function consulter(string $code): array
    {
        return $this->transport->request('GET', 'liens/'.self::seg($code));
    }

    /** Désactive le lien, définitivement. @return array<string, mixed> */
    public function desactiver(string $code): array
    {
        return $this->transport->request('DELETE', 'liens/'.self::seg($code));
    }
}
