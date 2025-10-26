
<?php
// =======================================================
// epi_search.php - Endpoint Dedicado para Autocomplete (AJAX)
// Este script só retorna dados JSON para o JavaScript.
// =======================================================

// Ajuste o caminho para o arquivo de conexão se necessário.
require_once 'conexao.php'; 

// Verifica se a requisição é válida e se a conexão existe
if (!isset($_GET['term']) || !$conexao_status || !isset($conn)) {
    // Retorna um array vazio e status 400 (Bad Request)
    http_response_code(400); 
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$termo = trim($_GET['term']);

// Garante que o termo de pesquisa tenha pelo menos 2 caracteres
if (empty($termo) || strlen($termo) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    $conn->close();
    exit;
}

// Lógica de busca de EPIs
$sql_autocomplete = "SELECT id_epi, nome_epi, ca FROM epis WHERE nome_epi LIKE ? LIMIT 10";

$sugestoes = [];
if ($stmt = $conn->prepare($sql_autocomplete)) {
    $termo_like = "%$termo%";
    $stmt->bind_param("s", $termo_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sugestoes[] = [
            'label' => $row['nome_epi'] . ' (CA: ' . $row['ca'] . ')',
            'value' => $row['nome_epi'],
            'id' => $row['id_epi']
        ];
    }
    $stmt->close();
} 

header('Content-Type: application/json');
echo json_encode($sugestoes);

$conn->close();
exit; // CRUCIAL: Impede que qualquer outro código seja executado após a resposta JSON.
?>