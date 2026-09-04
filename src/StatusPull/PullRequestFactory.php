<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

use Enthusiast\IntegrationRenderEngine\ConnectionTemplate;

final readonly class PullRequestFactory
{
    public function __construct(
        private ConnectionTemplate $template = new ConnectionTemplate(),
    ) {}

    /**
     * @param array<string, string> $static
     * @param array{from: string, to: string, page: string} $poll
     */
    public function build(string $connectionUrl, PullSpec $spec, array $static, array $poll): PullRequest
    {
        $path = (string) $this->template->render($this->applyPoll($spec->path, $poll), [], $static);

        $headerTemplate = $this->applyPoll($spec->headers, $poll);
        $headers = $this->template->renderHeaders(
            is_array($headerTemplate) ? $headerTemplate : [],
            [],
            $static,
        );

        $body = $spec->body === null ? null : $this->applyPoll($spec->body, $poll);
        if (is_array($body)) {
            $rendered = $this->template->render($body, [], $static);
            $body = is_array($rendered) ? $rendered : null;
        }
        if (is_array($body) && $spec->page() !== null && $spec->pageInBody()) {
            $param = $spec->pageParam();
            if (isset($body[$param]) && is_numeric($body[$param])) {
                $body[$param] = (int) $body[$param];
            }
        }

        if ($spec->page() !== null && !$spec->pageInBody()) {
            $path = $this->appendQuery($path, [$spec->pageParam() => $poll['page']]);
        }

        return new PullRequest(
            $spec->method,
            $this->joinUrl($connectionUrl, $path),
            $headers,
            $spec->method === PullSpec::METHOD_POST ? (is_array($body) ? $body : []) : null,
        );
    }

    /**
     * @param array{from: string, to: string, page: string} $poll
     */
    private function applyPoll(mixed $node, array $poll): mixed
    {
        if (is_string($node)) {
            return preg_replace_callback(
                '/\{\{\s*poll\.(from|to|page)\s*\}\}/',
                static fn (array $match): string => $poll[$match[1]] ?? '',
                $node,
            ) ?? $node;
        }

        if (is_array($node)) {
            return array_map(fn ($value) => $this->applyPoll($value, $poll), $node);
        }

        return $node;
    }

    private function joinUrl(string $base, string $path): string
    {
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        if ($host === '') {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        return $scheme . '://' . $host . $port . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    /**
     * @param array<string, string> $query
     */
    private function appendQuery(string $path, array $query): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';

        return $path . $separator . http_build_query($query);
    }
}
