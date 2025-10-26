<?php
// Arquivo: api_cep.php

require_once 'config_cep.php';

if (isset($_GET['cep'])) {
    $cep = preg_replace('/[^0-9]/', '', $_GET['cep']);

    if (strlen($cep) !== 8) {
        http_response_code(400);
        echo json_encode(['erro' => 'CEP inválido']);
        exit;
    }

    $uri = "https://www.cepaberto.com/api/v3/cep?cep=" . urlencode($cep);
    $type_request = "GET";

    $request = curl_init();
    curl_setopt($request, CURLOPT_HTTPHEADER, ['Authorization: Token ' . CEP_ABERTO_KEY]);
    curl_setopt($request, CURLOPT_URL, $uri);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($request, CURLOPT_CUSTOMREQUEST, $type_request);
    $response = curl_exec($request);
    $http_status = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    if ($http_status == 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo json_encode($data);
        } else {
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao decodificar a resposta da API']);
        }
    } else {
        http_response_code($http_status);
        echo json_encode(['erro' => 'CEP não encontrado ou erro na API externa']);
    }
} else {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetro "cep" não fornecido']);
}
?>
