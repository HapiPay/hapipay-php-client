<?php

namespace HapiPay\Client\Exceptions;

use RuntimeException;

/** Notification de webhook dont la signature est absente, fausse ou trop ancienne : à rejeter. */
class SignatureException extends RuntimeException {}
