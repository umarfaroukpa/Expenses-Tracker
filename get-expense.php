<?php
/**
 * api/expenses.php — REST endpoint
 *
 * GET    /api/expenses.php              → list (supports ?category=&month=&search=)
 * GET    /api/expenses.php?summary=1   → aggregated stats
 * GET    /api/expenses.php?categories=1
 * POST   /api/expenses.php              → create  (JSON body)
 * PUT    /api/expenses.php?id=xxx       → update  (JSON body)
 * DELETE /api/expenses.php?id=xxx       → delete
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/ExpenseStorage.php';

function json_ok(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_err(string $msg, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

try {
    $storage = new ExpenseStorage();
    $method  = $_SERVER['REQUEST_METHOD'];

    // ── GET ──────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        if (isset($_GET['summary']))    json_ok($storage->summary());
        if (isset($_GET['categories'])) json_ok($storage->categories());

        $filters = array_intersect_key($_GET, array_flip(['category', 'month', 'search']));
        json_ok($storage->all($filters));
    }

    // ── POST ─────────────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        json_ok($storage->create($body), 201);
    }

    // ── PUT ──────────────────────────────────────────────────────────────────
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? '';
        if (!$id) json_err('Missing id', 400);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        json_ok($storage->update($id, $body));
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) json_err('Missing id', 400);
        json_ok($storage->delete($id));
    }

    json_err('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    json_err($e->getMessage(), 422);
} catch (RuntimeException $e) {
    json_err($e->getMessage(), 404);
} catch (Throwable $e) {
    json_err('Internal server error: ' . $e->getMessage(), 500);
}
