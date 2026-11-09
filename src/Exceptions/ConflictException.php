<?php

namespace HapiPay\Client\Exceptions;

/** HTTP 409 : l'état ne permet pas l'action (marchand non vérifié, transfert déjà répondu, compte d'un autre type…). */
class ConflictException extends ApiException {}
