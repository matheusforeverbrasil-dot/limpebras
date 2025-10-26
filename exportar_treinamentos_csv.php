<?php
// Define que este script deve rodar por um longo tempo, se a lista for muito grande
set_time_limit(0); 

// --- 1. CONFIGURAÇÃO DO BANCO DE DADOS ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst');

// --- 2. CONEXÃO COM O BANCO DE DADOS ---
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // É importante usar die() aqui, pois não há interface gráfica para exibir o erro
    die("Erro de Conexão com o Banco de Dados: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- 3. FUNÇÃO AUXILIAR PARA BINDING SEGURO ---
function bindParamSafe($stmt, $types, &$values) {
    $bind_args = [];
    $bind_args[] = $types;
    
    // Passa os valores por referência
    foreach ($values as $key => $value) {
        $bind_args[] = &$values[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
}

// --- 4. TRATAMENTO DE FILTROS (MANTÉM A CONSISTÊNCIA COM O DASHBOARD) ---

// Coletar filtros da URL
$filter_funcao = isset($_GET['funcao']) && is_numeric($_GET['funcao']) ? (int)$_GET['funcao'] : null;
$filter_equipe = isset($_GET['equipe']) && is_numeric($_GET['equipe']) ? (int)$_GET['equipe'] : null;
$filter_tipo_treinamento = isset($_GET['tipo_treinamento']) && is_numeric($_GET['tipo_treinamento']) ? (int)$_GET['tipo_treinamento'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;
$filter_busca = isset($_GET['busca']) ? trim($_GET['busca']) : null;

// Construção da Cláusula WHERE
$where_clauses = ["1=1"];
$params_types = "";
$params_values = [];

// Filtro por Função
if ($filter_funcao) { $where_clauses[] = "c.funcao_id = ?"; $params_types .= "i"; $params_values[] = $filter_funcao; }
// Filtro por Equipe
if ($filter_equipe) { $where_clauses[] = "c.equipe_id = ?"; $params_types .= "i"; $params_values[] = $filter_equipe; }
// Filtro por Tipo de Treinamento
if ($filter_tipo_treinamento) { $where_clauses[] = "t.id_tipo_treinamento = ?"; $params_types .= "i"; $params_values[] = $filter_tipo_treinamento; }
// Filtro por Busca (Nome do Colaborador)
if (!empty($filter_busca)) { $where_clauses[] = "c.nome LIKE ?"; $params_types .= "s"; $params_values[] = "%" . $filter_busca . "%"; }

// Filtro por Status do Treinamento
$hoje = date('Y-m-d');
$limite30dias = date('Y-m-d', strtotime('+30 days'));

if ($filter_status == 'vencido') {
    $where_clauses[] = "t.data_vencimento < ?";
    $params_types .= "s";
    $params_values[] = $hoje;
} elseif ($filter_status == 'vencendo') {
    $where_clauses[] = "t.data_vencimento BETWEEN ? AND ?";
    $params_types .= "ss";
    $params_values[] = $hoje;
    $params_values[] = $limite30dias;
} elseif ($filter_status == 'em_dia') {
    $where_clauses[] = "t.data_vencimento > ?";
    $params_types .= "s";
    $params_values[] = $limite30dias;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);


// --- 5. QUERY DE DADOS PARA EXPORTAÇÃO (SEM PAGINAÇÃO) ---

$sql_export = "SELECT 
                t.data_vencimento,
                t.resp_treinamento,
                c.nome AS nome_colaborador,
                f.nome_funcao,
                e.nome_equipe,
                tt.nome_tipo_treinamento
              FROM 
                treinamentos t
              LEFT JOIN colaboradores c ON t.id_colaborador = c.id_colaborador
              LEFT JOIN funcoes f ON c.funcao_id = f.id_funcao
              LEFT JOIN equipes e ON c.equipe_id = e.id_equipe
              LEFT JOIN tipos_treinamento tt ON t.id_tipo_treinamento = tt.id_tipo_treinamento
              $where_sql
              ORDER BY c.nome ASC";

$result_export = null;

if ($stmt = $conn->prepare($sql_export)) {
    if ($params_types) {
        bindParamSafe($stmt, $params_types, $params_values);
    }
    
    $stmt->execute();
    $result_export = $stmt->get_result();
    $stmt->close();
}

$conn->close();

// --- 6. GERAÇÃO E DOWNLOAD DO CSV ---

// Funções auxiliares para status e formatação de data
function getStatusTexto($dataVencimento) {
    if (empty($dataVencimento)) return 'N/A';
    $dataVenc = strtotime($dataVencimento);
    $hoje = strtotime(date('Y-m-d'));
    $limite30dias = strtotime('+30 days', $hoje);

    if ($dataVenc < $hoje) { return 'VENCIDO'; } 
    elseif ($dataVenc <= $limite30dias) { return 'VENCENDO (30 DIAS)'; } 
    else { return 'EM DIA'; }
}
function formatarDataBR($dataSql) {
    return empty($dataSql) ? 'N/A' : date('d/m/Y', strtotime($dataSql));
}


// Definir cabeçalhos para forçar o download do arquivo CSV
header('Content-Type: text/csv; charset=utf-8'); 
header('Content-Disposition: attachment; filename="lista_treinamentos_' . date('Ymd_His') . '.csv"'); // Nome do arquivo com extensão .csv


// Abrir o buffer de saída (php://output)
$output = fopen('php://output', 'w');

// Cabeçalho para UTF-8 (BOM) - Garante que acentos sejam exibidos corretamente
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

// Cabeçalho do CSV (Nomes das colunas)
fputcsv($output, [
    'Status', 
    'Colaborador', 
    'Tipo de Treinamento', 
    'Função', 
    'Equipe', 
    'Data de Vencimento', 
    'Responsável'
], ';'); // Usando ponto e vírgula como delimitador (padrão Brasil)

// Preencher com os dados
if ($result_export && $result_export->num_rows > 0) {
    while ($row = $result_export->fetch_assoc()) {
        
        $status = getStatusTexto($row['data_vencimento']);
        $data_formatada = formatarDataBR($row['data_vencimento']);

        fputcsv($output, [
            $status,
            $row['nome_colaborador'],
            $row['nome_tipo_treinamento'],
            $row['nome_funcao'],
            $row['nome_equipe'],
            $data_formatada,
            $row['resp_treinamento']
        ], ';');
    }
}

// Fechar o buffer e finalizar
fclose($output);
exit;

?>