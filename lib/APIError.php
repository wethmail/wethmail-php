<?php
/**
 * Erro tipado devolvido pela façade WethMailClient.
 *
 * Ficheiro de overlay (sdk/overlays/php): é copiado para o pacote gerado.
 */

namespace WethMail\Client;

class APIError extends \Exception
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $requestId;

    public function __construct($statusCode, $message, $requestId = '')
    {
        parent::__construct($message, (int) $statusCode);
        $this->statusCode = (int) $statusCode;
        $this->requestId = $requestId;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Diz se a falha é transitória e vale a pena tentar de novo.
     *
     * @return bool
     */
    public function isRetryable()
    {
        return $this->statusCode === 0 || $this->statusCode === 429 || $this->statusCode >= 500;
    }

    public function __toString()
    {
        $prefix = 'wethmail: http ' . $this->statusCode . ': ' . $this->getMessage();
        return $this->requestId === '' ? $prefix : $prefix . ' (request_id=' . $this->requestId . ')';
    }
}
