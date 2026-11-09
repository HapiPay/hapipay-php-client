<?php

namespace HapiPay\Client\Exceptions;

/**
 * HapiPay injoignable (réseau, délai dépassé). Pour un paiement, rejouez
 * avec la même référence : l'API ne débite jamais deux fois.
 */
class ConnectionException extends ApiException {}
