<?php
// TEMPORAL - ELIMINAR DESPUÉS DEL DIAGNÓSTICO
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
echo json_encode([
    'raw_input'      => $raw,
    'raw_length'     => strlen($raw),
    'content_type'   => $_SERVER['HTTP_CONTENT_TYPE']  ?? $_SERVER['CONTENT_TYPE']  ?? null,
    'content_length' => $_SERVER['HTTP_CONTENT_LENGTH'] ?? $_SERVER['CONTENT_LENGTH'] ?? null,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'post_vars'      => $_POST,
    'server_software'=> $_SERVER['SERVER_SOFTWARE'] ?? null,
]);
