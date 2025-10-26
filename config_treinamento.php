<?php
// Arquivo: config_treinamento.php

// Substitua as constantes não definidas por variáveis simples
$servername = "localhost"; // O nome do servidor é 'localhost'
$username = "root";        // Seu usuário do banco de dados
$password = "";            // Sua senha (deixado em branco, conforme solicitado)
$dbname = "sistemasst";    // O nome do seu banco de dados

// Cria a conexão com o banco de dados usando as variáveis
$conexao = new mysqli($servername, $username, $password, $dbname);

// Verifica se a conexão foi bem-sucedida
if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}
?>

