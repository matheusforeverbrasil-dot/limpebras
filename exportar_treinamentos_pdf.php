<?php
// ATENÇÃO: PARA ESTE CÓDIGO FUNCIONAR, VOCÊ PRECISA BAIXAR E CONFIGURAR O DOMPDF.
require_once 'dompdf/autoload.inc.php'; // <--- VERIFIQUE ESTE CAMINHO!

use Dompdf\Dompdf;
use Dompdf\Options;

// --- 1. CONFIGURAÇÃO DO BANCO DE DADOS ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst');

// --- 2. CONEXÃO COM O BANCO DE DADOS ---
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    // ERRO NO NÍVEL DE CONEXÃO
    die("ERRO FATAL: Falha na Conexão com o Banco de Dados. Verifique DB_HOST, DB_USER, DB_PASS. Erro: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- 3. FUNÇÕES AUXILIARES ---
function bindParamSafe($stmt, $types, &$values) {
    $bind_args = [];
    $bind_args[] = $types;
    foreach ($values as $key => $value) {
        $bind_args[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
}
function getStatusCor($dataVencimento) {
    if (empty($dataVencimento)) return '#6c757d'; 
    $dataVenc = strtotime($dataVencimento);
    $hoje = strtotime(date('Y-m-d'));
    $limite30dias = strtotime('+30 days', $hoje);
    if ($dataVenc < $hoje) { return '#dc3545'; } 
    elseif ($dataVenc <= $limite30dias) { return '#ffc107'; } 
    else { return '#28a745'; }
}
function getStatusTexto($dataVencimento) {
    if (empty($dataVencimento)) return 'N/A';
    $dataVenc = strtotime($dataVencimento);
    $hoje = strtotime(date('Y-m-d'));
    $limite30dias = strtotime('+30 days', $hoje);
    
    if ($dataVenc < $hoje) { 
        return 'Vencido';
    } elseif ($dataVenc <= $limite30dias) { 
        return 'Vencendo';
    } else { 
        return 'Em Dia';
    }
}
function formatarDataBR($dataSql) {
    return empty($dataSql) ? 'N/A' : date('d/m/Y', strtotime($dataSql));
}

// --- 4. TRATAMENTO DE FILTROS ---

$filter_funcao = isset($_GET['funcao']) && is_numeric($_GET['funcao']) ? (int)$_GET['funcao'] : null;
$filter_equipe = isset($_GET['equipe']) && is_numeric($_GET['equipe']) ? (int)$_GET['equipe'] : null;
$filter_tipo_treinamento = isset($_GET['tipo_treinamento']) && is_numeric($_GET['tipo_treinamento']) ? (int)$_GET['tipo_treinamento'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;
$filter_busca = isset($_GET['busca']) ? trim($_GET['busca']) : null;

$where_clauses = ["1=1"];
$params_types = "";
$params_values = [];

if ($filter_funcao) { $where_clauses[] = "c.funcao_id = ?"; $params_types .= "i"; $params_values[] = $filter_funcao; }
if ($filter_equipe) { $where_clauses[] = "c.equipe_id = ?"; $params_types .= "i"; $params_values[] = $filter_equipe; }
if ($filter_tipo_treinamento) { $where_clauses[] = "t.id_tipo_treinamento = ?"; $params_types .= "i"; $params_values[] = $filter_tipo_treinamento; }
if (!empty($filter_busca)) { $where_clauses[] = "c.nome LIKE ?"; $params_types .= "s"; $params_values[] = "%" . $filter_busca . "%"; }

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


// --- 5. QUERY DE DADOS PARA EXPORTAÇÃO (Campo Removido) ---

$sql_export = "SELECT 
                t.data_vencimento,
                t.resp_treinamento,
                -- t.data_realizacao,  <<< CAMPO REMOVIDO PARA ELIMINAR O ERRO
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

$treinamentos = [];
if ($stmt = $conn->prepare($sql_export)) {
    if ($params_types) {
        bindParamSafe($stmt, $params_types, $params_values);
    }
    
    // VERIFICAÇÃO DE ERRO NA EXECUÇÃO
    if (!$stmt->execute()) {
        $error_message = "ERRO NA EXECUÇÃO DA CONSULTA: " . $stmt->error . "<br>";
        $error_message .= "SQL: " . htmlspecialchars($sql_export) . "<br>";
        $error_message .= "Tipos: " . $params_types . "<br>";
        $error_message .= "Valores: " . print_r($params_values, true);
        die($error_message);
    }

    $result_set = $stmt->get_result();

    if ($result_set && $result_set->num_rows > 0) {
        while ($row = $result_set->fetch_assoc()) {
            $treinamentos[] = $row;
        }
    }
    $stmt->close();
} else {
    // VERIFICAÇÃO DE ERRO NA PREPARAÇÃO
    $error_message = "ERRO NA PREPARAÇÃO DA CONSULTA: " . $conn->error . "<br>";
    $error_message .= "SQL: " . htmlspecialchars($sql_export);
    die($error_message);
}
$conn->close();


// --- 6. GERAÇÃO DO HTML PARA CONVERSÃO EM PDF (Ajustando as colunas da tabela) ---

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Treinamentos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        h1 { color: #34495e; font-size: 16pt; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .data-export { font-size: 8pt; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; font-size: 9pt; }
        .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
    </style>
</head>
<body>
    <h1>Relatório de Treinamentos</h1>
    <p class="data-export">Gerado em: ' . formatarDataBR(date('Y-m-d')) . ' às ' . date('H:i:s') . '</p>
    
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Status</th> 
                <th style="width: 20%;">Colaborador</th>
                <th style="width: 20%;">Tipo de Treinamento</th>
                <th style="width: 15%;">Função</th>
                <th style="width: 10%;">Equipe</th>
                <th style="width: 10%;">Vencimento</th>
                <th style="width: 10%;">Responsável</th>
                </tr>
        </thead>
        <tbody>';

if (count($treinamentos) > 0) {
    foreach ($treinamentos as $row) {
        $status_cor = getStatusCor($row['data_vencimento']);
        $status_texto = getStatusTexto($row['data_vencimento']);
        
        $html .= '
            <tr>
                <td>
                    <span class="status-indicator" style="background-color: ' . $status_cor . ';"></span>' . $status_texto . '
                </td>
                <td>' . htmlspecialchars($row['nome_colaborador']) . '</td>
                <td>' . htmlspecialchars($row['nome_tipo_treinamento']) . '</td>
                <td>' . htmlspecialchars($row['nome_funcao']) . '</td>
                <td>' . htmlspecialchars($row['nome_equipe']) . '</td>
                <td>' . formatarDataBR($row['data_vencimento']) . '</td>
                <td>' . htmlspecialchars($row['resp_treinamento']) . '</td>
            </tr>';
    }
} else {
    // Agora colspan="7" porque removemos a coluna 'Realização'
    $html .= '<tr><td colspan="7" style="text-align: center;">Nenhum registro encontrado com os filtros atuais.</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// --- 7. CONFIGURAÇÃO E GERAÇÃO DO PDF (DOMPDF) ---

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); 

$dompdf->render();

$filename = "relatorio_treinamentos_" . date('Ymd_His') . ".pdf";

// Força o download do arquivo
$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>