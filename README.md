# WethMail PHP SDK

PHP client for the WethMail v1 transactional email API (generator `php`,
namespace `WethMail\Client`). It wraps the generated OpenAPI client with a small
stable facade, typed errors and retries with exponential backoff (and jitter) on
transient failures.

Package: `wethmail/sdk` (Composer / Packagist).

## Install

```sh
composer require wethmail/sdk
```

## Quick start

```php
<?php

require 'vendor/autoload.php';

use WethMail\Client\WethMailClient;
use WethMail\Client\APIError;
use WethMail\Client\Model\CreateEmailRequest;

$client = new WethMailClient(getenv('WETHMAIL_API_KEY'), 'https://api.example.com');

$created = $client->sendEmail(new CreateEmailRequest([
    'from'    => 'no-reply@mail.example.com',
    'to'      => 'customer@example.com',
    'subject' => 'Confirm your address',
    'body'    => 'Hi, please confirm your email.',
]));

$email = $client->getEmail($created->getId());
echo 'status: ' . $email->getStatus() . PHP_EOL;
```

`sendEmail` enqueues the email and returns its id and initial status at once;
delivery is asynchronous. Poll `getEmail` until the status is `sent`, `failed`
or `bounced`. Models are built from an associative array.

## Configuration

`new WethMailClient($apiKey, $basePath = null, $maxRetries = 2, $baseDelayMs = 200, $maxDelayMs = 5000)`

| Parameter | Effect | Default |
|---|---|---|
| `$apiKey` | API key (Bearer) | — |
| `$basePath` | API base URL | `https://api.wethmail.local` |
| `$maxRetries` | Extra attempts on transient failure (`0` disables) | `2` |
| `$baseDelayMs` | Delay before the first retry, in ms (doubles, up to `$maxDelayMs`) | `200` |
| `$maxDelayMs` | Cap on the delay, in ms | `5000` |

## Errors

```php
try {
    $client->getEmail('missing-id');
} catch (APIError $e) {
    fwrite(STDERR, sprintf("http %d: %s (request_id=%s)\n",
        $e->getStatusCode(), $e->getMessage(), $e->getRequestId()));
    if ($e->isRetryable()) {
        // transient (network, 429 or 5xx)
    }
}
```

`getStatusCode()` is `0` on a network failure, `getMessage()` is the API `erro`
field and `getRequestId()` is the `X-Request-ID` header value.
