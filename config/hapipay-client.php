<?php

/*
| Client de l'API HapiPay. Noms de variables propres à ce paquet, pour ne
| pas se mêler à ceux d'autres paquets HapiPay installés dans la même
| application.
*/
return [
    // hpc_test_… en test, hpc_… en production. Jamais dans le code.
    'api_key' => env('HAPIPAY_CLIENT_API_KEY'),

    'base_url' => env('HAPIPAY_CLIENT_BASE_URL', 'https://pay.hapiditgroup.co/api/v1'),

    'timeout' => (float) env('HAPIPAY_CLIENT_TIMEOUT', 30),

    // Secret du webhook, affiché dans le portail (Développeurs → Webhooks).
    'webhook_secret' => env('HAPIPAY_CLIENT_WEBHOOK_SECRET'),
];
