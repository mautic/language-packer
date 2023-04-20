<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TransifexTestClient implements ClientInterface
{
    private ?RequestInterface $request   = null;
    private ?ResponseInterface $response = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!$this->response) {
            throw new class('The mock response has not been set.') extends \RuntimeException implements ClientExceptionInterface {};
        }

        $this->request = $request;

        return $this->response;
    }

    public function getRequest(): ?RequestInterface
    {
        if (!$this->request) {
            throw new \RuntimeException('The request has not been sent.');
        }

        return $this->request;
    }

    public function getResponse(): ?ResponseInterface
    {
        if (!$this->response) {
            throw new \RuntimeException('The response has not been set.');
        }

        return $this->response;
    }

    public function setResponse(?ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
