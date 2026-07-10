<?php

declare(strict_types=1);

function json_response(mixed $data, int $status = 200, string $contentType = 'application/json'): never
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function text_response(string $body, string $contentType = 'text/plain; charset=utf-8', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo $body;
    exit;
}

function wants_markdown(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (!str_contains($accept, 'text/markdown')) {
        return false;
    }
    // text/markdown wins if HTML isn't preferred outright
    return strpos($accept, 'text/markdown') < (strpos($accept, 'text/html') ?: PHP_INT_MAX);
}

// RFC 8288 Link headers for agent discovery
function send_discovery_links(): void
{
    $links = [
        '<' . app_url('/.well-known/api-catalog') . '>; rel="api-catalog"; type="application/linkset+json"',
        '<' . app_url('/.well-known/mcp/server-card.json') . '>; rel="service-desc"; title="MCP Server Card"',
        '<' . app_url('/llms.txt') . '>; rel="llms-txt"; type="text/plain"',
        '<' . app_url('/docs') . '>; rel="service-doc"; title="Agent Setup Docs"',
    ];
    header('Link: ' . implode(', ', $links));
}
