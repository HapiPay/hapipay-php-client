<?php

namespace HapiPay\Client\Resources;

use InvalidArgumentException;

/**
 * Marchands d'une plateforme : comptes marchands (identifiants d'opérateur)
 * et comptes particuliers (numéro mobile money personnel, transferts).
 */
final class Merchants extends Resource
{
    /**
     * Inscrit un marchand, ou met à jour sa fiche (même « reference ») :
     * appel rejouable. Champs : reference, nom, pays, telephone, email.
     *
     * @param  array<string, mixed>  $marchand
     * @return array<string, mixed>
     */
    public function inscrire(array $marchand): array
    {
        return $this->transport->request('POST', 'marchands', $marchand);
    }

    /**
     * Inscrit un vendeur en compte particulier. En plus des champs d'un
     * marchand : numero_encaissement (format international), nom_affiche
     * (le nom que montre son compte mobile money), operateur, et
     * numero_verifie (vous attestez avoir vérifié que ce numéro est le sien).
     *
     * @param  array<string, mixed>  $particulier
     * @return array<string, mixed>
     */
    public function inscrireParticulier(array $particulier): array
    {
        return $this->inscrire(['type' => 'particulier'] + $particulier);
    }

    /** @return array<string, mixed> */
    public function consulter(string $reference): array
    {
        return $this->transport->request('GET', 'marchands/'.self::seg($reference));
    }

    /**
     * Lien signé et temporaire à transmettre au marchand, vers la page où il
     * branche lui-même son compte d'opérateur. Compte marchand seulement.
     *
     * @return array{url: string, expire_le: string}
     */
    public function lienIdentifiants(string $reference): array
    {
        return $this->transport->request('POST', 'marchands/'.self::seg($reference).'/lien-identifiants');
    }

    /**
     * Pièce d'identité d'un vendeur particulier (JPG, PNG ou PDF), examinée
     * par HapiPay.
     *
     * @return array<string, mixed>
     */
    public function envoyerPieceIdentite(string $reference, string $recto, ?string $verso = null): array
    {
        $parts = [self::file('recto', $recto)];

        if ($verso !== null) {
            $parts[] = self::file('verso', $verso);
        }

        return $this->transport->request('POST', 'marchands/'.self::seg($reference).'/piece-identite', multipart: $parts);
    }

    /** @return array<string, mixed> */
    private static function file(string $name, string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Fichier illisible : {$path}");
        }

        return ['name' => $name, 'contents' => fopen($path, 'r'), 'filename' => basename($path)];
    }
}
