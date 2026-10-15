<?php
/**
 * Façade de conveniência para o cliente WethMail gerado (php).
 *
 * Ficheiro de overlay (sdk/overlays/php): é copiado para o pacote gerado e
 * acrescenta o que o cliente gerado não tem — uma API estável, erros tipados
 * (`WethMail\Client\APIError`) e retries com backoff para falhas transitórias
 * (rede, 429 e 5xx).
 */

namespace WethMail\Client;

use GuzzleHttp\Client as HttpClient;
use WethMail\Client\Api\DomainsApi;
use WethMail\Client\Api\EmailsApi;
use WethMail\Client\Api\StatsApi;

class WethMailClient
{
    const DEFAULT_BASE_PATH = 'https://api.wethmail.local';

    /** @var EmailsApi */
    private $emails;

    /** @var DomainsApi */
    private $domains;

    /** @var StatsApi */
    private $stats;

    /** @var int */
    private $maxRetries;

    /** @var int */
    private $baseDelayMs;

    /** @var int */
    private $maxDelayMs;

    public function __construct($apiKey, $basePath = null, $maxRetries = 2, $baseDelayMs = 200, $maxDelayMs = 5000)
    {
        $config = Configuration::getDefaultConfiguration()->setAccessToken($apiKey);
        $host = $basePath === null ? self::DEFAULT_BASE_PATH : $basePath;
        $config->setHost($this->stripTrailingSlashes($host));

        $client = new HttpClient();
        $this->emails = new EmailsApi($client, $config);
        $this->domains = new DomainsApi($client, $config);
        $this->stats = new StatsApi($client, $config);
        $this->maxRetries = (int) $maxRetries;
        $this->baseDelayMs = (int) $baseDelayMs;
        $this->maxDelayMs = (int) $maxDelayMs;
    }

    /**
     * @param \WethMail\Client\Model\CreateEmailRequest $request
     * @return \WethMail\Client\Model\EmailCreated
     * @throws APIError
     */
    public function sendEmail($request)
    {
        return $this->call(function () use ($request) {
            return $this->emails->createEmail($request);
        });
    }

    /**
     * @param string $id
     * @return \WethMail\Client\Model\Email
     * @throws APIError
     */
    public function getEmail($id)
    {
        return $this->call(function () use ($id) {
            return $this->emails->getEmail($id);
        });
    }

    /**
     * @param \WethMail\Client\Model\CreateDomainRequest $request
     * @return \WethMail\Client\Model\Domain
     * @throws APIError
     */
    public function createDomain($request)
    {
        return $this->call(function () use ($request) {
            return $this->domains->createDomain($request);
        });
    }

    /**
     * @return \WethMail\Client\Model\Domain[]
     * @throws APIError
     */
    public function listDomains()
    {
        return $this->call(function () {
            return $this->domains->listDomains();
        });
    }

    /**
     * @param string $id
     * @return \WethMail\Client\Model\Domain
     * @throws APIError
     */
    public function getDomain($id)
    {
        return $this->call(function () use ($id) {
            return $this->domains->getDomain($id);
        });
    }

    /**
     * @param string $id
     * @return \WethMail\Client\Model\DomainVerifyResponse
     * @throws APIError
     */
    public function verifyDomain($id)
    {
        return $this->call(function () use ($id) {
            return $this->domains->verifyDomain($id);
        });
    }

    /**
     * @param string $id
     * @return void
     * @throws APIError
     */
    public function deleteDomain($id)
    {
        return $this->call(function () use ($id) {
            $this->domains->deleteDomain($id);
            return null;
        });
    }

    /**
     * @param \DateTime|null $since
     * @return \WethMail\Client\Model\Stats
     * @throws APIError
     */
    public function stats($since = null)
    {
        return $this->call(function () use ($since) {
            return $this->stats->getStats($since);
        });
    }

    /**
     * @param callable $fn
     * @return mixed
     * @throws APIError
     */
    private function call(callable $fn)
    {
        $attempt = 0;
        while (true) {
            try {
                return $fn();
            } catch (ApiException $e) {
                $error = $this->mapError($e);
                if ($attempt >= $this->maxRetries || !$error->isRetryable()) {
                    throw $error;
                }
                $this->sleep($this->delayMs($attempt, $e));
                $attempt++;
            }
        }
    }

    /**
     * @param ApiException $e
     * @return APIError
     */
    private function mapError(ApiException $e)
    {
        $statusCode = (int) $e->getCode();
        $message = $e->getMessage();
        $body = $e->getResponseBody();

        if (is_object($body) && isset($body->erro) && is_string($body->erro) && $body->erro !== '') {
            $message = $body->erro;
        } elseif (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['erro']) && is_string($decoded['erro']) && $decoded['erro'] !== '') {
                $message = $decoded['erro'];
            }
        }

        if ($message === null || $message === '') {
            $message = 'erro desconhecido';
        }

        return new APIError($statusCode, $message, $this->headerValue($e->getResponseHeaders(), 'x-request-id'));
    }

    /**
     * @param int          $attempt
     * @param ApiException $e
     * @return int
     */
    private function delayMs($attempt, ApiException $e)
    {
        $retryAfter = $this->headerValue($e->getResponseHeaders(), 'retry-after');
        if ($retryAfter !== '' && ctype_digit($retryAfter)) {
            return ((int) $retryAfter) * 1000;
        }
        $delay = min($this->baseDelayMs * (1 << $attempt), $this->maxDelayMs);
        return (int) ($delay / 2 + mt_rand(0, max(0, (int) ($delay / 2))));
    }

    /**
     * @param mixed  $headers
     * @param string $name
     * @return string
     */
    private function headerValue($headers, $name)
    {
        if (!is_array($headers)) {
            return '';
        }
        foreach ($headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                if (is_array($values)) {
                    return isset($values[0]) ? (string) $values[0] : '';
                }
                return (string) $values;
            }
        }
        return '';
    }

    /**
     * @param string $basePath
     * @return string
     */
    private function stripTrailingSlashes($basePath)
    {
        if ($basePath === null || $basePath === '') {
            $basePath = self::DEFAULT_BASE_PATH;
        }
        return rtrim($basePath, '/');
    }

    /**
     * @param int $ms
     * @return void
     */
    private function sleep($ms)
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
