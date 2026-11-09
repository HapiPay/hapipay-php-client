<?php

namespace HapiPay\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HapiPay\Client\Exceptions\AuthenticationException;
use HapiPay\Client\Exceptions\ConflictException;
use HapiPay\Client\Exceptions\ConnectionException;
use HapiPay\Client\Exceptions\NotFoundException;
use HapiPay\Client\Exceptions\PaymentRequiredException;
use HapiPay\Client\Exceptions\ValidationException;
use HapiPay\Client\HapiPay;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $history = [];

    private function client(Response|\Throwable ...$responses): HapiPay
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new HapiPay('hpc_test_abc_def', ['base_url' => 'https://api.test/api/v1', 'http' => new Client(['handler' => $stack])]);
    }

    private static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function lastRequest(): Request
    {
        return end($this->history)['request'];
    }

    private function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?? [];
    }

    public function test_la_cle_est_controlee_et_le_mode_test_reconnu(): void
    {
        $this->assertTrue($this->client()->enTest());
        $this->assertFalse((new HapiPay('hpc_live_123'))->enTest());

        $this->expectException(InvalidArgumentException::class);
        new HapiPay('sk_live_123');
    }

    public function test_chaque_appel_porte_la_cle_et_renvoie_data(): void
    {
        $hapipay = $this->client(self::json(201, ['data' => ['id' => 'K3J', 'statut' => 'en_cours']]));

        $paiement = $hapipay->paiements()->creer('vendeur 42', ['reference' => 'CMD-1', 'montant' => 10000, 'operateur' => 'airtel', 'description' => null]);

        $this->assertSame(['id' => 'K3J', 'statut' => 'en_cours'], $paiement);
        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.test/api/v1/marchands/vendeur%2042/paiements', (string) $request->getUri());
        $this->assertSame('Bearer hpc_test_abc_def', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        // Les champs vides ne sont pas envoyés.
        $this->assertSame(['reference' => 'CMD-1', 'montant' => 10000, 'operateur' => 'airtel'], $this->lastBody());
    }

    public function test_un_marchand_direct_utilise_les_raccourcis(): void
    {
        $hapipay = $this->client(self::json(201, ['data' => []]), self::json(200, ['data' => []]), self::json(201, ['data' => []]));

        $hapipay->paiements()->creer(null, ['reference' => 'A', 'montant' => 1, 'operateur' => 'sandbox']);
        $this->assertSame('/api/v1/paiements', $this->lastRequest()->getUri()->getPath());

        $hapipay->paiements()->lister(null, 'CMD-1');
        $this->assertSame('reference=CMD-1', $this->lastRequest()->getUri()->getQuery());

        $hapipay->liens()->creer(null, ['montant' => 500]);
        $this->assertSame('/api/v1/liens', $this->lastRequest()->getUri()->getPath());
    }

    public function test_le_parcours_dun_transfert(): void
    {
        $ok = fn () => self::json(200, ['data' => ['id' => 'tr_1']]);
        $hapipay = $this->client(self::json(201, ['data' => ['id' => 'tr_1', 'instructions' => ['etapes' => []]]]), $ok(), $ok(), $ok(), $ok(), $ok(), $ok(), $ok());
        $t = $hapipay->transferts();

        $t->creer('afiavi', ['reference' => 'CMD-1', 'montant' => 5000, 'telephone_acheteur' => '+22901660000']);
        $this->assertSame('/api/v1/marchands/afiavi/transferts', $this->lastRequest()->getUri()->getPath());

        $t->declarerEnvoi('tr_1', 'MP2611');
        $this->assertSame(['/api/v1/transferts/tr_1/declaration', ['numero_transaction' => 'MP2611']], [$this->lastRequest()->getUri()->getPath(), $this->lastBody()]);

        $t->confirmerReception('tr_1');
        $this->assertSame(['reponse' => 'recu'], $this->lastBody());

        $t->signalerNonRecu('tr_1');
        $this->assertSame(['reponse' => 'non_recu'], $this->lastBody());

        $t->contester('tr_1', 'J\'ai le SMS');
        $this->assertSame('/api/v1/transferts/tr_1/contestation', $this->lastRequest()->getUri()->getPath());

        $t->rouvrir('tr_1');
        $this->assertSame('/api/v1/transferts/tr_1/reouverture', $this->lastRequest()->getUri()->getPath());

        $t->declarerRemboursement('tr_1', 5000, 'RB123456');
        $this->assertSame(['montant' => 5000, 'numero_transaction' => 'RB123456'], $this->lastBody());

        $t->simuler('tr_1', 'non_confirme');
        $this->assertSame('/api/v1/transferts/tr_1/test/simuler', $this->lastRequest()->getUri()->getPath());
    }

    public function test_un_particulier_sinscrit_avec_son_type(): void
    {
        $hapipay = $this->client(self::json(201, ['data' => ['type' => 'particulier']]));

        $hapipay->marchands()->inscrireParticulier(['reference' => 'afiavi', 'nom' => 'Afiavi', 'pays' => 'BJ', 'numero_encaissement' => '+2290197000000', 'nom_affiche' => 'AFIAVI', 'operateur' => 'mtn', 'numero_verifie' => true]);

        $this->assertSame('particulier', $this->lastBody()['type']);
        $this->assertTrue($this->lastBody()['numero_verifie']);
    }

    public function test_la_piece_didentite_part_en_multipart(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'id').'.jpg';
        file_put_contents($file, 'image');
        $hapipay = $this->client(self::json(202, ['data' => ['piece_identite' => ['statut' => 'pending']]]));

        $hapipay->marchands()->envoyerPieceIdentite('afiavi', $file);

        $this->assertStringStartsWith('multipart/form-data', $this->lastRequest()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="recto"', (string) $this->lastRequest()->getBody());
        unlink($file);
    }

    public function test_plafonds_usage_et_acheteurs(): void
    {
        $hapipay = $this->client(self::json(200, ['data' => []]), self::json(200, ['data' => ['mois' => '2026-11']]), self::json(200, ['data' => []]));

        $hapipay->plafondsParticuliers()->resserrer('bj', 'xof', ['par_transfert' => 100000]);
        $this->assertSame(['PUT', '/api/v1/plafonds-particuliers/BJ/XOF'], [$this->lastRequest()->getMethod(), $this->lastRequest()->getUri()->getPath()]);

        $this->assertSame(['mois' => '2026-11'], $hapipay->usage('2026-11'));
        $this->assertSame('mois=2026-11', $this->lastRequest()->getUri()->getQuery());

        $hapipay->acheteursSuspendus()->retablir('+22901660000');
        $this->assertSame('/api/v1/acheteurs-suspendus/%2B22901660000', $this->lastRequest()->getUri()->getPath());
    }

    public function test_les_erreurs_deviennent_des_exceptions_avec_le_message_de_lapi(): void
    {
        $cases = [
            [401, AuthenticationException::class],
            [402, PaymentRequiredException::class],
            [404, NotFoundException::class],
            [409, ConflictException::class],
        ];

        foreach ($cases as [$status, $class]) {
            try {
                $this->client(self::json($status, ['message' => "Erreur {$status}"]))->plateforme();
                $this->fail("Pas d'exception pour {$status}");
            } catch (\Throwable $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertSame("Erreur {$status}", $e->getMessage());
                $this->assertSame($status, $e->status);
            }
        }
    }

    public function test_une_erreur_de_validation_donne_les_champs(): void
    {
        try {
            $this->client(self::json(422, ['message' => 'Le montant dépasse le plafond.', 'errors' => ['montant' => ['Trop élevé.']]]))
                ->transferts()->creer('afiavi', ['reference' => 'X', 'montant' => 999999, 'telephone_acheteur' => '+229']);
            $this->fail('Pas d\'exception');
        } catch (ValidationException $e) {
            $this->assertSame('Le montant dépasse le plafond.', $e->getMessage());
            $this->assertSame(['montant' => ['Trop élevé.']], $e->errors());
        }
    }

    public function test_hapipay_injoignable(): void
    {
        $this->expectException(ConnectionException::class);

        $this->client(new ConnectException('délai dépassé', new Request('GET', 'x')))->plateforme();
    }
}
