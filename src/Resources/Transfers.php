<?php

namespace HapiPay\Client\Resources;

/**
 * Transferts vers les comptes particuliers. L'acheteur envoie l'argent
 * depuis son téléphone vers le numéro du vendeur ; HapiPay ne le voit pas.
 * La réception est confirmée par le vendeur, jamais par l'opérateur :
 * ne réglez une vente que sur « recu_confirme » (webhook transfert.confirme).
 */
final class Transfers extends Resource
{
    /**
     * Prépare un transfert. Champs : reference, montant, telephone_acheteur,
     * et facultatifs devise, nom_acheteur, description, langue. La réponse
     * contient « instructions » : à afficher telles quelles à l'acheteur.
     *
     * @param  array<string, mixed>  $transfert
     * @return array<string, mixed>
     */
    public function creer(string $marchand, array $transfert): array
    {
        return $this->transport->request('POST', 'marchands/'.self::seg($marchand).'/transferts', $transfert);
    }

    /** @return list<array<string, mixed>> */
    public function lister(string $marchand, ?string $reference = null): array
    {
        return $this->transport->request('GET', 'marchands/'.self::seg($marchand).'/transferts', query: ['reference' => $reference]);
    }

    /** État du transfert et sa chronologie (preuves en cas de litige). @return array<string, mixed> */
    public function consulter(string $id): array
    {
        return $this->transport->request('GET', self::path($id));
    }

    /** L'acheteur a envoyé : numéro de transaction du SMS de son opérateur. @return array<string, mixed> */
    public function declarerEnvoi(string $id, string $numeroTransaction): array
    {
        return $this->transport->request('POST', self::path($id, 'declaration'), ['numero_transaction' => $numeroTransaction]);
    }

    /** Réponse du vendeur : « Reçu ». Seule issue qui règle la vente. @return array<string, mixed> */
    public function confirmerReception(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'confirmation'), ['reponse' => 'recu']);
    }

    /** Réponse du vendeur : « Pas reçu ». @return array<string, mixed> */
    public function signalerNonRecu(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'confirmation'), ['reponse' => 'non_recu']);
    }

    /** Annule, tant qu'aucun envoi n'est déclaré. @return array<string, mixed> */
    public function annuler(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'annulation'));
    }

    /** L'acheteur conteste un « non reçu » ou un « non confirmé ». @return array<string, mixed> */
    public function contester(string $id, string $motif): array
    {
        return $this->transport->request('POST', self::path($id, 'contestation'), ['motif' => $motif]);
    }

    /** Votre arbitrage : la contestation est close, l'issue reste. @return array<string, mixed> */
    public function annulerContestation(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'contestation/annulation'));
    }

    /** Votre arbitrage : le vendeur doit répondre de nouveau. @return array<string, mixed> */
    public function rouvrir(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'reouverture'));
    }

    /** Le vendeur a renvoyé l'argent lui-même. @return array<string, mixed> */
    public function declarerRemboursement(string $id, int $montant, string $numeroTransaction): array
    {
        return $this->transport->request('POST', self::path($id, 'remboursement'), ['montant' => $montant, 'numero_transaction' => $numeroTransaction]);
    }

    /** L'acheteur confirme avoir reçu le remboursement. @return array<string, mixed> */
    public function confirmerRemboursement(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'remboursement/confirmation'), ['reponse' => 'recu']);
    }

    /** L'acheteur dit ne pas avoir reçu le remboursement. @return array<string, mixed> */
    public function contesterRemboursement(string $id): array
    {
        return $this->transport->request('POST', self::path($id, 'remboursement/confirmation'), ['reponse' => 'non_recu']);
    }

    /**
     * Mode test seulement : provoque tout de suite « relance »,
     * « non_confirme » ou « expire ».
     *
     * @return array<string, mixed>
     */
    public function simuler(string $id, string $evenement): array
    {
        return $this->transport->request('POST', self::path($id, 'test/simuler'), ['evenement' => $evenement]);
    }

    private static function path(string $id, ?string $action = null): string
    {
        return 'transferts/'.self::seg($id).($action === null ? '' : '/'.$action);
    }
}
