<?php

namespace HapiPay\Client\Webhooks;

use HapiPay\Client\Exceptions\SignatureException;

/**
 * Vérification des notifications de webhook HapiPay.
 *
 * En-tête « HapiPay-Signature: t=<horodatage>,v1=<hmac> », avec
 * hmac = HMAC-SHA256("<horodatage>.<corps brut>", secret du webhook).
 * Pendant les 24 h qui suivent un changement de secret, l'en-tête porte
 * deux « v1 » : la notification est valable si l'un d'eux correspond.
 *
 *     $evenement = Webhook::verifier($corpsBrut, $enTeteSignature, $secret);
 *     if ($evenement['evenement'] === 'paiement.reussi') { … }
 */
final class Webhook
{
    public const HEADER = 'HapiPay-Signature';

    /** Écart maximal accepté avec l'horloge, en secondes (rejeu d'une vieille notification). */
    public const TOLERANCE = 300;

    /**
     * Vérifie la signature et renvoie la notification décodée :
     * id, evenement, test, cree_le, data. Une même notification peut
     * arriver deux fois : ignorez un « id » déjà traité.
     *
     * @return array<string, mixed>
     *
     * @throws SignatureException
     */
    public static function verifier(string $payload, ?string $signatureHeader, string $secret, int $tolerance = self::TOLERANCE, ?int $now = null): array
    {
        if ($secret === '') {
            throw new SignatureException('Secret de webhook manquant.');
        }

        [$timestamp, $signatures] = self::parse((string) $signatureHeader);

        if ($timestamp === null || $signatures === []) {
            throw new SignatureException('En-tête '.self::HEADER.' absent ou mal formé.');
        }

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new SignatureException('Notification trop ancienne ou horodatage invalide.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $valid = false;
        foreach ($signatures as $signature) {
            $valid = $valid || hash_equals($expected, $signature);
        }

        if (! $valid) {
            throw new SignatureException('Signature de webhook invalide.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new SignatureException('Corps de notification illisible.');
        }

        return $event;
    }

    /** Variante sans exception. */
    public static function estValide(string $payload, ?string $signatureHeader, string $secret, int $tolerance = self::TOLERANCE): bool
    {
        try {
            self::verifier($payload, $signatureHeader, $secret, $tolerance);

            return true;
        } catch (SignatureException) {
            return false;
        }
    }

    /**
     * Les événements des transferts (comptes particuliers) : à ne jamais
     * traiter comme un paiement.
     */
    public static function estUnTransfert(array $event): bool
    {
        $name = (string) ($event['evenement'] ?? '');

        return str_starts_with($name, 'transfert.') || str_starts_with($name, 'particulier.') || str_starts_with($name, 'acheteur.');
    }

    /** @return array{0: ?int, 1: list<string>} */
    private static function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_string($value) && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
