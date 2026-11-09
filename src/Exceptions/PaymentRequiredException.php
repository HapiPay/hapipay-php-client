<?php

namespace HapiPay\Client\Exceptions;

/** HTTP 402 : en production, pas de formule active, identité non vérifiée ou plafond de la formule atteint. */
class PaymentRequiredException extends ApiException {}
