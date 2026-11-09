<?php

namespace HapiPay\Client\Exceptions;

/** HTTP 401 : clé d'API absente, inconnue ou révoquée, ou compte suspendu. */
class AuthenticationException extends ApiException {}
