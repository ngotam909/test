<?php
use Utils\Response;

if (!function_exists('json')) {
    /** @return never */
    function json(mixed $data, int $status = 200)
    {
        Response::json($data, $status);
    }
}

if (!function_exists('read_json')) {
    function read_json(): array
    {
        return Response::read_json();
    }
}
