<?php

namespace HapiPay\Client;

use GuzzleHttp\ClientInterface;
use HapiPay\Client\Resources\BlockedBuyers;
use HapiPay\Client\Resources\IndividualLimits;
use HapiPay\Client\Resources\Links;
use HapiPay\Client\Resources\Merchants;
use HapiPay\Client\Resources\Payments;
use HapiPay\Client\Resources\Transfers;
use InvalidArgumentException;

/**
 * Client de l'API HapiPay.
 *
 *     $hapipay = new HapiPay('hpc_test_…');
 *     $paiement = $hapipay->paiements()->creer('vendeur-42', ['reference' => 'CMD-1', 'montant' => 10000, 'operateur' => 'airtel', 'telephone' => '+23566000000']);
 *
 * Une clé hpc_test_… travaille en mode test (aucun argent réel), une clé
 * hpc_… en production. Les réponses sont les tableaux « data » de l'API,
 * avec ses noms de champs (voir la documentation de l'API).
 */
final class HapiPay
{
    public const DEFAULT_BASE_URL = 'https://pay.hapiditgroup.co/api/v1';

    private readonly Transport $transport;

    /**
     * @param  array{base_url?: string, timeout?: float, http?: ClientInterface}  $options
     */
    public function __construct(private readonly string $apiKey, array $options = [])
    {
        if (! str_starts_with($apiKey, 'hpc_')) {
            throw new InvalidArgumentException('Clé d\'API HapiPay invalide : elle commence par « hpc_ » (production) ou « hpc_test_ » (test).');
        }

        $this->transport = new Transport(
            $apiKey,
            $options['base_url'] ?? self::DEFAULT_BASE_URL,
            (float) ($options['timeout'] ?? 30.0),
            $options['http'] ?? null,
        );
    }

    /** Clé de test (hpc_test_…) : marchands, paiements et transferts simulés. */
    public function enTest(): bool
    {
        return str_starts_with($this->apiKey, 'hpc_test_');
    }

    /** Le compte reconnu par la clé : pour vérifier une clé. @return array<string, mixed> */
    public function plateforme(): array
    {
        return $this->transport->request('GET', 'plateforme');
    }

    /**
     * Usage du mois (AAAA-MM, mois en cours par défaut) : paiements et,
     * à part, transferts.
     *
     * @return array<string, mixed>
     */
    public function usage(?string $mois = null): array
    {
        return $this->transport->request('GET', 'usage', query: ['mois' => $mois]);
    }

    public function marchands(): Merchants
    {
        return new Merchants($this->transport);
    }

    public function paiements(): Payments
    {
        return new Payments($this->transport);
    }

    public function liens(): Links
    {
        return new Links($this->transport);
    }

    /** Comptes particuliers : transferts confirmés par le vendeur, jamais des paiements. */
    public function transferts(): Transfers
    {
        return new Transfers($this->transport);
    }

    public function plafondsParticuliers(): IndividualLimits
    {
        return new IndividualLimits($this->transport);
    }

    public function acheteursSuspendus(): BlockedBuyers
    {
        return new BlockedBuyers($this->transport);
    }
}
