<?php

namespace HapiPay\Client\Resources;

/**
 * Paiements : confirmés par l'opérateur, l'argent va directement sur le
 * compte du marchand. Pour un compte de marchand direct, passez null comme
 * référence de marchand.
 */
final class Payments extends Resource
{
    /**
     * Lance un paiement. Champs : reference (votre commande, rejouable sans
     * double débit), montant, operateur, telephone, devise, description,
     * url_retour et url_annulation (paiement sur la page de l'opérateur :
     * envoyez alors le payeur vers « redirection »).
     *
     * @param  array<string, mixed>  $paiement
     * @return array<string, mixed>
     */
    public function creer(?string $marchand, array $paiement): array
    {
        return $this->transport->request('POST', self::base($marchand), $paiement);
    }

    /** Paiements d'un marchand (50 derniers), éventuellement d'une seule commande. @return list<array<string, mixed>> */
    public function lister(?string $marchand, ?string $reference = null): array
    {
        return $this->transport->request('GET', self::base($marchand), query: ['reference' => $reference]);
    }

    /**
     * État d'un paiement. $actualiser interroge l'opérateur : à réserver au
     * retour du payeur sur votre page.
     *
     * @return array<string, mixed>
     */
    public function consulter(string $id, bool $actualiser = false): array
    {
        return $this->transport->request('GET', 'paiements/'.self::seg($id), query: ['actualiser' => $actualiser ? 1 : null]);
    }

    /** Rembourse un paiement réussi, en tout ($montant null) ou en partie. @return array<string, mixed> */
    public function rembourser(string $id, ?int $montant = null): array
    {
        return $this->transport->request('POST', 'paiements/'.self::seg($id).'/remboursements', ['montant' => $montant]);
    }

    private static function base(?string $marchand): string
    {
        return $marchand === null ? 'paiements' : 'marchands/'.self::seg($marchand).'/paiements';
    }
}
