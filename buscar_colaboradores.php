<?php
// Arquivo: buscar_colaboradores.php
include 'conexao.php'; 

header('Content-Type: application/json');

if (isset($_GET['termo'])) {
    $termo = $conn->real_escape_string($_GET['termo']);
    $search_term = "%" . $termo . "%";

    // ****** CORREÇÃO AQUI: INCLUINDO id_colaborador no SELECT ******
    $sql = "SELECT id_colaborador, nome, matricula 
            FROM colaboradores 
            WHERE nome LIKE ? OR matricula LIKE ? 
            ORDER BY nome 
            LIMIT 10"; 

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $colaboradores = [];
        
        while ($row = $result->fetch_assoc()) {
            $colaboradores[] = $row;
        }
        
        echo json_encode($colaboradores);
        
        $stmt->close();
    } else {
        error_log('Erro na preparação da query Colaboradores: ' . $conn->error);
        echo json_encode(['error' => 'Erro interno na busca.']);
    }
} else {
    echo json_encode([]);
}

$conn->close();
// Não feche a tag PHP no final para evitar espaços em branco/erros de JSON.