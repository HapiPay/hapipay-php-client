# Client PHP de l'API HapiPay

Encaissez par mobile money, par carte et par PayPal depuis votre application PHP, pour votre commerce ou au nom des vendeurs de votre plateforme. **L'argent ne passe jamais par HapiPay** : il va directement du payeur au compte du marchand.

- PHP 8.1 ou plus, sans framework obligatoire.
- Intégration Laravel incluse : configuration, injection du client, vérification des webhooks.
- Documentation complète de l'API : <https://pay.hapiditgroup.co/developpeurs/documentation>

## Installation

```bash
composer require hapipay/php-client
```

## Démarrer

Créez une clé dans le portail HapiPay (**Développeurs → Clés d'API**). Une clé `hpc_test_…` travaille en mode test, sans argent réel ; une clé `hpc_…` en production. Gardez-la hors du code (variable d'environnement).

```php
use HapiPay\Client\HapiPay;

$hapipay = new HapiPay(getenv('HAPIPAY_CLIENT_API_KEY'));

$paiement = $hapipay->paiements()->creer('vendeur-42', [
    'reference' => 'CMD-1',          // votre commande : rejouer la même référence ne débite jamais deux fois
    'montant' => 10000,
    'operateur' => 'airtel',
    'telephone' => '+23566000000',
]);

// Plus tard, ou au retour du payeur :
$paiement = $hapipay->paiements()->consulter($paiement['id'], actualiser: true);
```

Les réponses sont les tableaux `data` de l'API, avec ses noms de champs. Pour un compte de marchand direct (vous encaissez pour vous-même), passez `null` à la place de la référence du marchand.

## Ce que couvre le client

| Méthode | API |
|---|---|
| `marchands()->inscrire()`, `consulter()`, `lienIdentifiants()` | Marchands d'une plateforme |
| `marchands()->inscrireParticulier()`, `envoyerPieceIdentite()` | Comptes particuliers |
| `paiements()->creer()`, `lister()`, `consulter()`, `rembourser()` | Paiements |
| `liens()->creer()`, `consulter()`, `desactiver()` | Pages de paiement et QR codes |
| `transferts()->creer()`, `declarerEnvoi()`, `confirmerReception()`, `signalerNonRecu()`, `annuler()`… | Transferts (comptes particuliers) |
| `transferts()->contester()`, `rouvrir()`, `annulerContestation()`, `declarerRemboursement()`… | Litiges et remboursements des transferts |
| `plafondsParticuliers()->lister()`, `resserrer()`, `retablir()` | Plafonds des comptes particuliers |
| `acheteursSuspendus()->lister()`, `retablir()` | Numéros d'acheteurs suspendus |
| `usage()`, `plateforme()` | Usage du mois, vérification de la clé |

## Comptes particuliers et transferts

Pour un vendeur sans compte marchand chez un opérateur (réservé aux plateformes) : l'acheteur envoie l'argent par un transfert ordinaire, depuis son téléphone, vers le numéro mobile money du vendeur. **HapiPay ne voit pas ce transfert : la réception est confirmée par le vendeur, pas par l'opérateur.** Ne réglez une vente que sur `recu_confirme`.

```php
$hapipay->marchands()->inscrireParticulier([
    'reference' => 'afiavi',
    'nom' => 'Afiavi Hounkpatin',
    'pays' => 'BJ',
    'numero_encaissement' => '+229 01 97 00 00 00',
    'nom_affiche' => 'AFIAVI HOUNKPATIN',   // le nom que son compte mobile money affiche
    'operateur' => 'mtn',
    'numero_verifie' => true,               // vous attestez avoir vérifié ce numéro
]);

$transfert = $hapipay->transferts()->creer('afiavi', [
    'reference' => 'CMD-1',
    'montant' => 15000,
    'telephone_acheteur' => '+229 01 66 00 00 00',
    'langue' => 'fr',
]);

// À montrer TEL QUEL à l'acheteur : la marche à suivre et l'avertissement sur le nom attendu.
$transfert['instructions']['etapes'];
$transfert['instructions']['avertissement'];

// Après l'envoi, l'acheteur donne le numéro de transaction de son SMS…
$hapipay->transferts()->declarerEnvoi($transfert['id'], 'MP261109.1234');
// …puis le vendeur répond.
$hapipay->transferts()->confirmerReception($transfert['id']);   // ou signalerNonRecu()
```

En test, `transferts()->simuler($id, 'relance' | 'non_confirme' | 'expire')` provoque tout de suite ce que les délais feraient plus tard.

## Erreurs

Chaque erreur de l'API devient une exception, avec le message de l'API (en français) et `->status` :

| Exception | HTTP | Quand |
|---|---|---|
| `AuthenticationException` | 401 | Clé absente, inconnue ou révoquée |
| `PaymentRequiredException` | 402 | Production : pas de formule active, identité non vérifiée, plafond atteint |
| `ForbiddenException` | 403 | Action non permise à ce compte |
| `NotFoundException` | 404 | Marchand, paiement ou transfert inconnu de ce compte |
| `ConflictException` | 409 | L'état ne permet pas l'action |
| `ValidationException` | 422 | Champs refusés (`->errors()`), plafond dépassé |
| `RateLimitException` | 429 | Trop d'appels |
| `ConnectionException` | — | HapiPay injoignable : rejouez avec la même référence |

Toutes héritent de `HapiPay\Client\Exceptions\ApiException`.

## Webhooks

HapiPay prévient votre application par un `POST` signé. **Vérifiez toujours la signature** avant de livrer :

```php
use HapiPay\Client\Webhooks\Webhook;

$evenement = Webhook::verifier(
    file_get_contents('php://input'),
    $_SERVER['HTTP_HAPIPAY_SIGNATURE'] ?? null,
    getenv('HAPIPAY_CLIENT_WEBHOOK_SECRET'),
); // SignatureException si elle est fausse, absente ou trop ancienne

if ($evenement['evenement'] === 'paiement.reussi') {
    // livrer la commande $evenement['data']['reference']
}
```

Pendant les 24 h qui suivent un changement de secret, les deux secrets sont acceptés. Une même notification peut arriver deux fois : ignorez un `id` déjà traité. Les événements des transferts (`transfert.*`) ne sont envoyés qu'aux webhooks qui les ont cochés ; `Webhook::estUnTransfert($evenement)` permet de ne jamais les confondre avec un paiement.

## Laravel

Le paquet s'enregistre tout seul. Dans `.env` :

```dotenv
HAPIPAY_CLIENT_API_KEY=hpc_test_…
HAPIPAY_CLIENT_WEBHOOK_SECRET=…
```

```php
use HapiPay\Client\HapiPay;

public function payer(HapiPay $hapipay) { … }   // client injecté

// routes/web.php : route exclue de la protection CSRF
Route::post('webhooks/hapipay', WebhookController::class)->middleware('hapipay.webhook');

// dans le contrôleur : la notification vérifiée
$evenement = $request->attributes->get('hapipay_evenement');
```

Pour modifier la configuration : `php artisan vendor:publish --tag=hapipay-client`.

## Licence

MIT.
