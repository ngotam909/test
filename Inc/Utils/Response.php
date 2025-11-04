<?php
namespace Utils;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function read_json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return [];

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::json(['error' => 'Invalid JSON'], 400);
        }
        return $data ?: [];
    }
}
