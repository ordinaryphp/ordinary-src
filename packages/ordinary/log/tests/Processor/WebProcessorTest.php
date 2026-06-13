<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Processor;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Processor\WebProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(WebProcessor::class)]
final class WebProcessorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fakeServerParams(): array
    {
        return [
            'REMOTE_ADDR'     => '10.0.0.1',
            'SERVER_NAME'     => 'example.com',
            'HTTP_X_REQUEST_ID' => 'req-abc',
        ];
    }

    private function fakeRequest(
        string $uri = 'https://example.com/api/users/42',
        string $method = 'POST',
        string $referrer = 'https://example.com/dashboard',
        string $userAgent = 'Mozilla/5.0',
    ): ServerRequestInterface {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('__toString')->willReturn($uri);
        $rawHost = \parse_url($uri, \PHP_URL_HOST);
        $uriStub->method('getHost')->willReturn(\is_string($rawHost) ? $rawHost : '');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')->willReturnMap([
            ['Referer', $referrer],
            ['User-Agent', $userAgent],
        ]);
        $request->method('getServerParams')->willReturn($this->fakeServerParams());

        return $request;
    }

    // ── PSR-7 request mode ────────────────────────────────────────────────────

    #[Test]
    public function it_reads_url_from_psr_uri(): void
    {
        $processor = new WebProcessor($this->fakeRequest());
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('https://example.com/api/users/42', $result->context['request.url']);
    }

    #[Test]
    public function it_reads_method_from_psr_request(): void
    {
        $processor = new WebProcessor($this->fakeRequest(method: 'DELETE'));
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('DELETE', $result->context['request.method']);
    }

    #[Test]
    public function it_reads_server_name_from_psr_uri_host(): void
    {
        $processor = new WebProcessor($this->fakeRequest());
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('example.com', $result->context['request.server']);
    }

    #[Test]
    public function it_falls_back_to_server_name_param_when_uri_has_no_host(): void
    {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('__toString')->willReturn('/relative/path');
        $uriStub->method('getHost')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getServerParams')->willReturn(['SERVER_NAME' => 'fallback.example.com']);

        $result = new WebProcessor($request)->process(
            new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()),
        );

        $this->assertSame('fallback.example.com', $result->context['request.server']);
    }

    #[Test]
    public function it_reads_referrer_from_referer_header(): void
    {
        $processor = new WebProcessor($this->fakeRequest(referrer: 'https://example.com/dashboard'));
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('https://example.com/dashboard', $result->context['request.referrer']);
    }

    #[Test]
    public function it_reads_user_agent_from_header(): void
    {
        $processor = new WebProcessor($this->fakeRequest(userAgent: 'MyBot/1.0'));
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('MyBot/1.0', $result->context['request.user_agent']);
    }

    #[Test]
    public function it_reads_ip_from_server_params(): void
    {
        $processor = new WebProcessor($this->fakeRequest());
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('10.0.0.1', $result->context['request.ip']);
    }

    #[Test]
    public function it_reads_extra_fields_from_server_params(): void
    {
        $processor = new WebProcessor($this->fakeRequest(), extraFields: ['HTTP_X_REQUEST_ID']);
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('req-abc', $result->context['request.http_x_request_id']);
    }

    #[Test]
    public function it_falls_back_to_server_params_when_psr_method_is_empty(): void
    {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('__toString')->willReturn('');
        $uriStub->method('getHost')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getMethod')->willReturn('');
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getServerParams')->willReturn([
            'REQUEST_METHOD' => 'PATCH',
            'REQUEST_URI'    => '/fallback',
            'HTTP_REFERER'   => 'https://fallback.example.com',
        ]);

        $result = new WebProcessor($request)->process(
            new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()),
        );

        $this->assertSame('PATCH', $result->context['request.method']);
        $this->assertSame('/fallback', $result->context['request.url']);
        $this->assertSame('https://fallback.example.com', $result->context['request.referrer']);
    }

    #[Test]
    public function it_returns_item_unchanged_when_psr_request_yields_no_context(): void
    {
        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('__toString')->willReturn('');
        $uriStub->method('getHost')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getMethod')->willReturn('');
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getServerParams')->willReturn([]);

        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['a' => 1]);
        $result = new WebProcessor($request)->process($item);

        $this->assertSame($item, $result);
    }

    // ── Array mode ────────────────────────────────────────────────────────────

    #[Test]
    public function it_adds_standard_fields_from_array(): void
    {
        $processor = new WebProcessor([
            'REQUEST_URI'     => '/api/users/42',
            'REMOTE_ADDR'     => '10.0.0.1',
            'REQUEST_METHOD'  => 'POST',
            'SERVER_NAME'     => 'example.com',
            'HTTP_REFERER'    => 'https://example.com/dashboard',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('/api/users/42', $result->context['request.url']);
        $this->assertSame('10.0.0.1', $result->context['request.ip']);
        $this->assertSame('POST', $result->context['request.method']);
        $this->assertSame('example.com', $result->context['request.server']);
        $this->assertSame('https://example.com/dashboard', $result->context['request.referrer']);
        $this->assertSame('Mozilla/5.0', $result->context['request.user_agent']);
    }

    #[Test]
    public function it_skips_missing_array_fields(): void
    {
        $result = new WebProcessor(['REQUEST_URI' => '/test'])->process(
            new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()),
        );

        $this->assertArrayHasKey('request.url', $result->context);
        $this->assertArrayNotHasKey('request.ip', $result->context);
    }

    #[Test]
    public function it_includes_extra_fields_from_array(): void
    {
        $processor = new WebProcessor(
            ['REQUEST_URI' => '/test', 'HTTP_X_REQUEST_ID' => 'abc123'],
            extraFields: ['HTTP_X_REQUEST_ID'],
        );
        $result = $processor->process(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        $this->assertSame('abc123', $result->context['request.http_x_request_id']);
    }

    #[Test]
    public function it_returns_item_unchanged_when_array_has_no_matching_keys(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['a' => 1]);
        $result = new WebProcessor([])->process($item);

        $this->assertSame($item, $result);
    }

    // ── Null mode ─────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_item_unchanged_when_null(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['a' => 1]);
        $result = new WebProcessor()->process($item);

        $this->assertSame($item, $result);
    }

    // ── Common ────────────────────────────────────────────────────────────────

    #[Test]
    public function it_preserves_existing_context(): void
    {
        $processor = new WebProcessor($this->fakeRequest());
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['user_id' => 99]);

        $result = $processor->process($item);

        $this->assertSame(99, $result->context['user_id']);
        $this->assertArrayHasKey('request.url', $result->context);
    }

    #[Test]
    public function it_does_not_mutate_original_item(): void
    {
        $processor = new WebProcessor($this->fakeRequest());
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor->process($item);

        $this->assertArrayNotHasKey('request.url', $item->context);
    }
}
