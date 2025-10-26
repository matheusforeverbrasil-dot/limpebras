<?php
require_once 'header.php'; 
if ($conn->connect_error) {

    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Falha na conexão do servidor.']));
}

// 3. DEFINE O CABEÇALHO PARA JSON
header('Conetnt-Type: application/json');

// Inicializa a lista de resultados vazia
$cids = [];

// 4. VERIFICA O TERMO DE BUSCA
if (isset($_GET['termo'])) {
    $termo_busca = trim($_GET['termo']);
    
    // Confirma o requisito mínimo de 1 ou 3 caracteres para o PHP (opcional, mas bom)
    if (strlen($termo_busca) >= 1) { 
        
        // Constrói o termo de busca para a consulta LIKE
        $search_param = "%" . $termo_busca . "%";

        // 5. QUERY SQL (COM NOME DE TABELA/COLUNAS CONFIRMADOS)
        // Certifique-se de que 'CID', 'sigla' e 'descricao' estão EXATOS
        $sql = "SELECT sigla, descricao 
                FROM CID 
                WHERE sigla LIKE ? OR descricao LIKE ? 
                ORDER BY sigla 
                LIMIT 10"; 

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Liga os parâmetros (ss = string, string)
            $stmt->bind_param("ss", $search_param, $search_param);
            
            // 6. EXECUTA E BUSCA RESULTADOS
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $cids[] = $row;
                }
                
                $result->free();
            } else {
                 // Erro na execução da query (ex: dados ruins)
                 error_log('Erro na execução da query CID: ' . $stmt->error);
            }
            
            $stmt->close();
        } else {
            // Erro na preparação da query (ex: nome de coluna ou tabela errado)
            error_log('Erro na preparação da query CID: ' . $conn->error);
        }
    }
}

// 7. RETORNA O RESULTADO (MESMO QUE SEJA UM ARRAY VAZIO '[]')
// A função json_encode garante que a saída será válida para o JavaScript
echo json_encode($cids);

require_once 'footer.php';
?>