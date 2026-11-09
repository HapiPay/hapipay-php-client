<?php

namespace HapiPay\Client\Tests;

use HapiPay\Client\Exceptions\SignatureException;
use HapiPay\Client\Webhooks\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_nouveau';

    private static function sign(string $body, int $t, string $secret = self::SECRET): string
    {
        return 'v1='.hash_hmac('sha256', $t.'.'.$body, $secret);
    }

    public function test_une_notification_signee_est_acceptee_et_decodee(): void
    {
        $body = '{"id":"01JC","evenement":"paiement.reussi","test":false,"data":{"id":"K3J"}}';
        $t = time();

        $event = Webhook::verifier($body, "t={$t},".self::sign($body, $t), self::SECRET);

        $this->assertSame('paiement.reussi', $event['evenement']);
        $this->assertFalse(Webhook::estUnTransfert($event));
    }

    public function test_pendant_un_changement_de_secret_lune_des_deux_signatures_suffit(): void
    {
        $body = '{"evenement":"transfert.confirme"}';
        $t = time();
        $header = "t={$t},".self::sign($body, $t).','.self::sign($body, $t, 'whsec_ancien');

        $this->assertTrue(Webhook::estValide($body, $header, self::SECRET));
        $this->assertTrue(Webhook::estValide($body, $header, 'whsec_ancien'));
        $this->assertTrue(Webhook::estUnTransfert(Webhook::verifier($body, $header, self::SECRET)));
    }

    public function test_une_signature_fausse_absente_ou_ancienne_est_refusee(): void
    {
        $body = '{"evenement":"paiement.reussi"}';
        $t = time();

        $this->assertFalse(Webhook::estValide($body, "t={$t},".self::sign($body, $t, 'autre'), self::SECRET));
        $this->assertFalse(Webhook::estValide($body, null, self::SECRET));
        $this->assertFalse(Webhook::estValide($body.' ', "t={$t},".self::sign($body, $t), self::SECRET), 'corps modifié');

        $old = $t - 600;
        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('trop ancienne');
        Webhook::verifier($body, "t={$old},".self::sign($body, $old), self::SECRET);
    }
}
