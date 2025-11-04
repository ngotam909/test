<?php
use Inc\DB;
use Inc\BookService;
use Middleware\Access;

require __DIR__ . '/../Inc/Helpers.php';

// ---- CORS ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id, X-User-Role');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    header('Content-Length: 0');
    exit;
}

// ---- router ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$db  = new DB();
$svc = new BookService($db);

try {
    // 1) GET /api/books?q=&author=&page=&perPage=
    if ($method === 'GET' && $path === '/api/books') {
        $q = $_GET['q'] ?? null;
        $author = $_GET['author']  ?? null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 20;

        $filters = array_filter([
            'q' => $q,
            'author' => $author,
        ], fn($v) => $v !== null && $v !== '');

        $result = $svc->list($filters, $page, $perPage);
        json($result);
    }

    // 2) POST /api/books (Admin only)
    if ($method === 'POST' && $path === '/api/books') {
        // Trả về user_id nếu OK
        $uid = Access::requireRole('admin');

        $payload = read_json();
        $newId = $svc->create($payload);
        json(['id' => $newId, 'created_by' => $uid], 201);
    }

    // 3) POST /api/borrow  body: { "book_id": 123, "due_date": "YYYY-MM-DD"? }
    if ($method === 'POST' && $path === '/api/borrow') {
        $uid = Access::requireLogin();

        $payload = read_json();
        $bookId  = (int)($payload['book_id'] ?? 0);
        if ($bookId <= 0) json(['error' => 'book_id is required'], 422);

        $due = isset($payload['due_date']) && $payload['due_date'] !== '' ? (string)$payload['due_date'] : null;

        $loanId = $svc->borrow($uid, $bookId, $due);
        json(['loan_id' => $loanId, 'book_id' => $bookId], 201);
    }

    // 4) PUT /api/return/{bookId}
    if ($method === 'PUT' && preg_match('#^/api/return/(\d+)$#', $path, $m)) {
        $uid    = Access::requireLogin();
        $bookId = (int)$m[1];

        $ok = $svc->returnBook($uid, $bookId);
        if ($ok) {
            http_response_code(204);
            exit;
        }
        json(['error' => 'Return failed'], 400);
    }

    // 404 fallback
    json(['error' => 'Not Found'], 404);

} catch (\RuntimeException $e) {
    json(['error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    json(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
