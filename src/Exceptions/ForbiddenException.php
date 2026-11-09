<?php

namespace HapiPay\Client\Exceptions;

/** HTTP 403 : action non permise à ce compte (formule, type de compte, clé de production pour une simulation…). */
class ForbiddenException extends ApiException {}
