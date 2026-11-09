<?php

namespace HapiPay\Client\Exceptions;

/** HTTP 429 : trop d'appels ; réessayez un peu plus tard. */
class RateLimitException extends ApiException {}
