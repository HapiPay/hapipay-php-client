<?php

namespace HapiPay\Client\Laravel;

use Closure;
use HapiPay\Client\Exceptions\SignatureException;
use HapiPay\Client\Webhooks\Webhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse (401) toute notification dont la signature HapiPay est fausse,
 * absente ou trop ancienne. La notification vérifiée est ensuite dans
 * $request->attributes->get('hapipay_evenement').
 *
 *     Route::post('webhooks/hapipay', WebhookController::class)->middleware('hapipay.webhook');
 *
 * Excluez cette route de la protection CSRF.
 */
class VerifyHapiPayWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = Webhook::verifier(
                $request->getContent(),
                $request->header(Webhook::HEADER),
                (string) config('hapipay-client.webhook_secret'),
            );
        } catch (SignatureException $e) {
            abort(401, $e->getMessage());
        }

        $request->attributes->set('hapipay_evenement', $event);

        return $next($request);
    }
}
