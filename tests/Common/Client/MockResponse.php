<?php

declare(strict_types=1);

namespace App\Tests\Common\Client;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

class MockResponse
{
    private \Closure $callback;
    private ?string $assertRequestUri    = null;
    private ?string $assertRequestMethod = null;
    private ?string $assertRequestBody   = null;

    /**
     * @var array<string, mixed>
     */
    private ?array $assertRequestHeaders;

    private function __construct(callable $callback)
    {
        $this->callback = \Closure::fromCallable($callback);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public static function fromString(string $body = null, int $status = 200, array $headers = []): self
    {
        return new self(fn () => new Response($status, $headers, $body));
    }

    public static function fromCallback(callable $callback): self
    {
        return new self($callback);
    }

    /**
     * @param mixed[] $options
     */
    public function __invoke(RequestInterface $request, array $options): Response
    {
        if (isset($this->assertRequestUri)) {
            Assert::assertSame($this->assertRequestUri, (string) $request->getUri());
        }

        if (isset($this->assertRequestMethod)) {
            Assert::assertSame($this->assertRequestMethod, $request->getMethod());
        }

        if (isset($this->assertRequestBody)) {
            Assert::assertSame($this->assertRequestBody, $request->getBody()->getContents());
        }

        if (isset($this->assertRequestHeaders)) {
            Assert::assertSame($this->assertRequestHeaders, $request->getHeaders());
        }

        return ($this->callback)($request, $options);
    }

    public function assertRequestUri(?string $assertRequestUri): self
    {
        $this->assertRequestUri = $assertRequestUri;

        return $this;
    }

    public function assertRequestMethod(?string $assertRequestMethod): self
    {
        $this->assertRequestMethod = $assertRequestMethod;

        return $this;
    }

    public function assertRequestBody(?string $assertRequestBody): self
    {
        $this->assertRequestBody = $assertRequestBody;

        return $this;
    }

    /**
     * @param array<string, mixed> $assertRequestHeaders
     */
    public function assertRequestHeaders(?array $assertRequestHeaders): self
    {
        $this->assertRequestHeaders = $assertRequestHeaders;

        return $this;
    }
}
