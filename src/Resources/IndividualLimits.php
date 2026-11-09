<?php

namespace HapiPay\Client\Resources;

/** Plafonds des comptes particuliers : ceux de HapiPay, que vous pouvez seulement resserrer. */
final class IndividualLimits extends Resource
{
    /** @return list<array<string, mixed>> par pays et devise : appliques, hapipay, plateforme */
    public function lister(): array
    {
        return $this->transport->request('GET', 'plafonds-particuliers');
    }

    /**
     * Champs : par_transfert, par_mois_montant, par_mois_nombre,
     * par_acheteur_par_jour, seuil_piece_identite (null = celui de HapiPay).
     *
     * @param  array<string, int|null>  $plafonds
     * @return array<string, mixed>
     */
    public function resserrer(string $pays, string $devise, array $plafonds): array
    {
        return $this->transport->request('PUT', 'plafonds-particuliers/'.self::seg(strtoupper($pays)).'/'.self::seg(strtoupper($devise)), $plafonds);
    }

    /** Revient aux plafonds de HapiPay. @return array<string, mixed> */
    public function retablir(string $pays, string $devise): array
    {
        return $this->transport->request('DELETE', 'plafonds-particuliers/'.self::seg(strtoupper($pays)).'/'.self::seg(strtoupper($devise)));
    }
}
