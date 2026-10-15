<?php
declare(strict_types=1);

require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Service.php';

use HPChapter\ApiError;
use HPChapter\Service;
use HPChapter\Storage;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

try {
    $action = $_GET['action'] ?? '';
    $getActions = ['config', 'preview', 'leaderboard'];
    $postActions = ['create', 'start', 'answer', 'next', 'finish', 'state', 'abandon'];
    if (!is_string($action) || !in_array($action, [...$getActions, ...$postActions], true)) {
        throw new ApiError(404, 'Unknown API action.');
    }
    $method = in_array($action, $getActions, true) ? 'GET' : 'POST';
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        throw new ApiError(405, 'Use ' . $method . ' for this action.');
    }
    if (array_diff(array_keys($_GET), $action === 'leaderboard' ? ['action', 'mode', 'limit'] : ['action'])) {
        throw new ApiError(400, 'Unknown query parameters.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null) {
        // No forwarding headers: origin comparison uses only the direct host and TLS state.
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!preg_match('/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]+)?$/iD', $host)
            || strtolower($origin) !== strtolower($scheme . '://' . $host)) {
            throw new ApiError(403, 'Requests must come from this site.');
        }
    }
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'cross-site') {
        throw new ApiError(403, 'Cross-site requests are not allowed.');
    }
    if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 4096) {
        throw new ApiError(413, 'Request body exceeds 4096 bytes.');
    }
    $body = [];
    if ($method === 'POST') {
        if (!preg_match('/^application\/json(?:\s*;.*)?$/iD', $_SERVER['CONTENT_TYPE'] ?? '')) {
            throw new ApiError(415, 'Send an application/json request body.');
        }
        $stream = fopen('php://input', 'rb');
        $source = stream_get_contents($stream, 4097);
        fclose($stream);
        if (strlen($source) > 4096) {
            throw new ApiError(413, 'Request body exceeds 4096 bytes.');
        }
        try {
            $decoded = json_decode($source, false, 16, JSON_THROW_ON_ERROR);
            if (!$decoded instanceof stdClass) {
                throw new ApiError(400, 'The request body must be a JSON object.');
            }
            $body = (array) $decoded;
            if (isset($body['selectedBooks']) && $body['selectedBooks'] instanceof stdClass) {
                throw new ApiError(400, 'selectedBooks must be a JSON array.');
            }
        } catch (JsonException) {
            throw new ApiError(400, 'Malformed JSON request body.');
        }
    }
    $storage = new Storage();
    $service = new Service($storage);
    $storage->rateLimit($_SERVER['REMOTE_ADDR'] ?? 'unknown', $service->now(), $action === 'create');
    if ($action === 'config') {
        $response = $service->rules;
    } elseif ($action === 'preview') {
        $boards = [];
        foreach (Service::MODES as $mode) {
            $boards[$mode] = $service->leaderboard($mode, 10)['entries'];
        }
        $response = ['boards' => $boards];
    } elseif ($action === 'leaderboard') {
        $limit = $_GET['limit'] ?? '100';
        if (!is_string($limit) || !preg_match('/^[1-9][0-9]{0,2}$/D', $limit)) {
            throw new ApiError(400, 'Invalid leaderboard limit.');
        }
        $response = $service->leaderboard($_GET['mode'] ?? 'normal', (int) $limit);
    } elseif ($action === 'create') {
        $response = $service->create($body);
    } else {
        $response = $service->act($action, $body);
    }
    echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ApiError $error) {
    http_response_code($error->status);
    if ($error->status === 429) {
        header('Retry-After: ' . ($error->retryAfter ?? 60));
    }
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(503);
    echo '{"error":"The game database or server is unavailable. Please try again later."}';
}
