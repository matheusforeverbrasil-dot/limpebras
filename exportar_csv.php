<?php
// =================================================================
// EXPORTAR_CSV.PHP - Exporta a Ficha de Entrega de EPI para CSV
// =================================================================
// 🚨 ATENÇÃO: Credenciais (Ajuste conforme seu ambiente)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst'); 

if (!isset($_GET['ordem'])) {
    die("Número de Ordem não especificado.");
}

$numero_ordem = $_GET['ordem'];

// Conexão com o MySQL
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Erro de Conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ------------------------------------------------
// 1. BUSCA DOS DADOS DA FICHA DE ENTREGA
// ------------------------------------------------
$sql = "
    SELECT 
        e.numero_ordem, 
        e.data_entrega, 
        c.nome AS nome_colaborador, 
        c.matricula, 
        c.setor,
        e.observacao,
        de.quantidade_movimentada, 
        p.nome_epi,
        p.ca
    FROM entregas e
    JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
    JOIN detalhes_entrega de ON e.id_entrega = de.id_entrega
    JOIN epis p ON de.id_epi = p.id_epi
    WHERE e.numero_ordem = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $numero_ordem);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Ordem de entrega #{$numero_ordem} não encontrada.");
}

$dados_csv = [];
$header_escrito = false;

while ($row = $result->fetch_assoc()) {
    // Monta a linha do CSV
    $linha = [
        'N_ORDEM' => $row['numero_ordem'],
        'DATA_ENTREGA' => date('d/m/Y H:i:s', strtotime($row['data_entrega'])),
        'COLABORADOR' => $row['nome_colaborador'],
        'MATRICULA' => $row['matricula'],
        'SETOR' => $row['setor'],
        'NOME_EPI' => $row['nome_epi'],
        'CA_EPI' => $row['ca'],
        'QUANTIDADE_RETIRADA' => $row['quantidade_movimentada'],
        'OBSERVACAO' => $row['observacao']
    ];
    
    // Na primeira iteração, captura o cabeçalho (nomes das colunas)
    if (!$header_escrito) {
        $header_csv = array_keys($linha);
        $dados_csv[] = $header_csv;
        $header_escrito = true;
    }
    
    $dados_csv[] = array_values($linha);
}

$stmt->close();
$conn->close();

// ------------------------------------------------
// 2. GERAÇÃO E SAÍDA DO ARQUIVO CSV
// ------------------------------------------------

// Configura o cabeçalho para forçar o download do CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Ficha_EPI_' . $numero_ordem . '.csv');

// Abre a saída (buffer de PHP) para escrita
$output = fopen('php://output', 'w');

// Adiciona o BOM (Byte Order Mark) para garantir a compatibilidade com acentuação no Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

// Escreve os dados no buffer
foreach ($dados_csv as $row) {
    // Usa ponto-e-vírgula como delimitador (padrão brasileiro)
    fputcsv($output, $row, ';'); 
}

fclose($output);
exit();