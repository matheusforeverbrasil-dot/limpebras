<?php
require_once 'header.php';  
// --- 3. FUNÇÃO DE BUSCA E LISTAGEM DE DADOS DOS FILTROS (SELECTS) ---
function getDropdownData($conn, $table, $id_col, $name_col) {
    $data = [];
    $result = $conn->query("SELECT $id_col, $name_col FROM $table ORDER BY $name_col ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

$funcoes_list = getDropdownData($conn, 'funcoes', 'id_funcao', 'nome_funcao');
$equipes_list = getDropdownData($conn, 'equipes', 'id_equipe', 'nome_equipe');
$tipos_treinamento_list = getDropdownData($conn, 'tipos_treinamento', 'id_tipo_treinamento', 'nome_tipo_treinamento');

// --- 4. FUNÇÃO PARA BINDING SEGURO ---
function bindParamSafe($stmt, $types, &$values) {
    $bind_args = [];
    $bind_args[] = $types;
    
    foreach ($values as $key => $value) {
        $bind_args[] = &$values[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
}

// --- 5. TRATAMENTO DE FILTROS E PAGINAÇÃO ---

$pageSize = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $pageSize;

// Coletar filtros da URL
$filter_funcao = isset($_GET['funcao']) && is_numeric($_GET['funcao']) ? (int)$_GET['funcao'] : null;
$filter_equipe = isset($_GET['equipe']) && is_numeric($_GET['equipe']) ? (int)$_GET['equipe'] : null;
$filter_tipo_treinamento = isset($_GET['tipo_treinamento']) && is_numeric($_GET['tipo_treinamento']) ? (int)$_GET['tipo_treinamento'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null; // 'em_dia', 'vencendo', 'vencido'
$filter_busca = isset($_GET['busca']) ? trim($_GET['busca']) : null; // Busca por nome

// Construção da Cláusula WHERE
$where_clauses = ["1=1"];
$params_types = "";
$params_values = [];

// Filtro por Função
if ($filter_funcao) {
    $where_clauses[] = "c.funcao_id = ?";
    $params_types .= "i";
    $params_values[] = $filter_funcao;
}
// Filtro por Equipe
if ($filter_equipe) {
    $where_clauses[] = "c.equipe_id = ?";
    $params_types .= "i";
    $params_values[] = $filter_equipe;
}
// Filtro por Tipo de Treinamento
if ($filter_tipo_treinamento) {
    $where_clauses[] = "t.id_tipo_treinamento = ?";
    $params_types .= "i";
    $params_values[] = $filter_tipo_treinamento;
}
// Filtro por Busca (Nome do Colaborador)
if (!empty($filter_busca)) {
    $where_clauses[] = "c.nome LIKE ?";
    $params_types .= "s";
    $params_values[] = "%" . $filter_busca . "%";
}

// Filtro por Status do Treinamento (Aplica-se à tabela de resultados)
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


// --- 6. CÁLCULO DOS KPIS DE STATUS (IGNORA OS FILTROS DA TABELA) ---

$kpi_contagens = ['em_dia' => 0, 'vencendo' => 0, 'vencido' => 0];

// KPI VENCIDO
$sql_vencido = "SELECT COUNT(id_treinamento) AS total FROM treinamentos WHERE data_vencimento < ?";
if ($stmt = $conn->prepare($sql_vencido)) {
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $kpi_contagens['vencido'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// KPI VENCENDO
$sql_vencendo = "SELECT COUNT(id_treinamento) AS total FROM treinamentos WHERE data_vencimento BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_vencendo)) {
    $stmt->bind_param("ss", $hoje, $limite30dias);
    $stmt->execute();
    $kpi_contagens['vencendo'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// KPI EM DIA
$sql_em_dia = "SELECT COUNT(id_treinamento) AS total FROM treinamentos WHERE data_vencimento > ?";
if ($stmt = $conn->prepare($sql_em_dia)) {
    $stmt->bind_param("s", $limite30dias);
    $stmt->execute();
    $kpi_contagens['em_dia'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}


// --- 7. BUSCAR DADOS DA TABELA (APLICA OS FILTROS) ---

// 7.1 Query de Contagem (para paginação)
$sql_count = "SELECT COUNT(t.id_treinamento) AS total
              FROM treinamentos t
              LEFT JOIN colaboradores c ON t.id_colaborador = c.id_colaborador
              $where_sql";

$stmt_count = $conn->prepare($sql_count);
if ($params_types) { bindParamSafe($stmt_count, $params_types, $params_values); }
$stmt_count->execute();
$totalItems = $stmt_count->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $pageSize);
$stmt_count->close();

// 7.2 Query da Tabela (com LIMIT e OFFSET)
$sql_table = "SELECT 
                t.id_treinamento,
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
              ORDER BY t.data_vencimento ASC
              LIMIT ? OFFSET ?";

$params_types_full = $params_types . "ii";
$params_values_full = array_merge($params_values, [$pageSize, $offset]);

$treinamentos = [];
if ($stmt = $conn->prepare($sql_table)) {
    bindParamSafe($stmt, $params_types_full, $params_values_full);
    
    $stmt->execute();
    $result_table = $stmt->get_result();

    if ($result_table->num_rows > 0) {
        while ($row = $result_table->fetch_assoc()) {
            $treinamentos[] = $row;
        }
    }
    $stmt->close();
}

// Fechar conexão (Será reaberta se necessário em outros arquivos)
$conn->close();

// --- 8. FUNÇÕES AUXILIARES DE FORMATAÇÃO E FILTRO ---

function formatarData($dataSql) {
    if (empty($dataSql)) return 'N/A';
    $timestamp = strtotime($dataSql);
    return date('d/m/Y', $timestamp);
}

// Esta função PRECISA ser mantida, pois o estilo da cor será aplicado inline no HTML
function getStatusCor($dataVencimento) {
    if (empty($dataVencimento)) return '#6c757d'; // Cinza (N/A)

    $dataVenc = strtotime($dataVencimento);
    $hoje = strtotime(date('Y-m-d'));
    $limite30dias = strtotime('+30 days', $hoje);

    if ($dataVenc < $hoje) {
        return '#dc3545'; // Vermelho (Vencido)
    } elseif ($dataVenc <= $limite30dias) {
        return '#ffc107'; // Amarelo (Vencendo)
    } else {
        return '#28a745'; // Verde (Em dia)
    }
}

function buildQueryString($exclude_keys = []) {
    $params = $_GET;
    foreach ($exclude_keys as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}

// -------------------------------------------------------------------
// INCLUSÃO DO CABEÇALHO (header.php) - INÍCIO DA SAÍDA HTML
// -------------------------------------------------------------------
require_once 'header.php';
?>
<h1>
    Controle de Treinamentos
</h1>
    
<div class="filter-area">
    
    <form method="GET" action="dashboard_treinamento.php" class="filter-form-row">
        
        <div class="filter-group">
            <label for="busca">Nome/Busca Rápida</label>
            <input type="text" id="busca" name="busca" value="<?php echo htmlspecialchars($filter_busca ?? ''); ?>" placeholder="Nome do Colaborador">
        </div>

        <div class="filter-group">
            <label for="funcao">Função</label>
            <select id="funcao" name="funcao">
                <option value="">Todas</option>
                <?php foreach ($funcoes_list as $f): ?>
                    <option value="<?php echo $f['id_funcao']; ?>" <?php echo ($filter_funcao == $f['id_funcao'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($f['nome_funcao']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="equipe">Equipe</label>
            <select id="equipe" name="equipe">
                <option value="">Todas</option>
                <?php foreach ($equipes_list as $e): ?>
                    <option value="<?php echo $e['id_equipe']; ?>" <?php echo ($filter_equipe == $e['id_equipe'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($e['nome_equipe']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="tipo_treinamento">Tipo de Treinamento</label>
            <select id="tipo_treinamento" name="tipo_treinamento">
                <option value="">Todos</option>
                <?php foreach ($tipos_treinamento_list as $tt): ?>
                    <option value="<?php echo $tt['id_tipo_treinamento']; ?>" <?php echo ($filter_tipo_treinamento == $tt['id_tipo_treinamento'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($tt['nome_tipo_treinamento']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <button type="submit">Aplicar Filtros</button>
        </div>

    </form>
    
    <div class="filter-form-row" style="align-items: flex-start; justify-content: space-between;">

        <div class="filter-group" style="min-width: 250px;">
            <label>Exportar Dados Filtrados</label>
            
            <a href="exportar_treinamentos_csv.php?<?php echo buildQueryString(['page']); ?>" 
                class="export-button-link" style="margin-bottom: 10px; background-color: #3498db;">
                Exportar para CSV (.csv) 
            </a>
            <a href="exportar_treinamentos_pdf.php?<?php echo buildQueryString(['page']); ?>" 
               class="export-button-link" style="background-color: #e74c3c;">
                Exportar para PDF
            </a>
        </div>

        <div class="filter-group" style="flex-grow: 1;">
            <label>Filtrar por Status</label>
            <div class="status-buttons">
                <?php 
                $current_query = buildQueryString(['status', 'page']);
                ?>
                <a href="?<?php echo $current_query; ?>" class="<?php echo (empty($filter_status) ? 'status-active' : ''); ?>">Todos</a>
                <a href="?<?php echo $current_query; ?>&status=em_dia" class="em-dia <?php echo ($filter_status == 'em_dia' ? 'status-active' : ''); ?>">Em Dia</a>
                <a href="?<?php echo $current_query; ?>&status=vencendo" class="vencendo <?php echo ($filter_status == 'vencendo' ? 'status-active' : ''); ?>">Vencendo (30 dias)</a>
                <a href="?<?php echo $current_query; ?>&status=vencido" class="vencido <?php echo ($filter_status == 'vencido' ? 'status-active' : ''); ?>">Vencido</a>
            </div>
        </div>

    </div>
</div>


<div class="kpi-container">
    <div class="kpi-card">
        <h3>Treinamentos Vencidos</h3>
        <p class="kpi-value vencidos"><?php echo $kpi_contagens['vencido']; ?></p>
    </div>

    <div class="kpi-card">
        <h3>Vencendo (Próx. 30 dias)</h3>
        <p class="kpi-value vencendo"><?php echo $kpi_contagens['vencendo']; ?></p>
    </div>

    <div class="kpi-card">
        <h3>Treinamentos Em Dia</h3>
        <p class="kpi-value em-dia"><?php echo $kpi_contagens['em_dia']; ?></p>
    </div>

    <div class="kpi-card" style="flex-grow: 1;">
        <h3>Registros na Tabela (Filtro Atual)</h3>
        <p class="kpi-value total-filtrado"><?php echo $totalItems; ?></p>
    </div>
</div>

<p>Treinamentos Encontrados (Página <?php echo $page; ?> de <?php echo $totalPages; ?>)</p>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Colaborador</th>
                <th>Tipo de Treinamento</th>
                <th>Função</th>
                <th>Equipe</th>
                <th>Data de Vencimento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($treinamentos) > 0): ?>
                <?php foreach ($treinamentos as $treinamento): ?>
                    <tr>
                        <td>
                            <span class="status-indicator" style="background-color: <?php echo getStatusCor($treinamento['data_vencimento']); ?>;"></span>
                        </td>
                        <td><?php echo htmlspecialchars($treinamento['nome_colaborador'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($treinamento['nome_tipo_treinamento'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($treinamento['nome_funcao'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($treinamento['nome_equipe'] ?? 'N/A'); ?></td>
                        <td><?php echo formatarData($treinamento['data_vencimento']); ?></td>
                        <td>
                            <a href="editar_treinamento.php?id=<?php echo $treinamento['id_treinamento']; ?>" class="btn-edit">
                                Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">Nenhum treinamento encontrado com os filtros atuais.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php
    $current_query_base = buildQueryString(['page']);
    
    $maxLinks = 5;
    $start = max(1, $page - floor($maxLinks / 2));
    $end = min($totalPages, $page + floor($maxLinks / 2));
    
    if ($page > 1) { echo '<a href="?' . $current_query_base . '&page=' . ($page - 1) . '">&laquo; Anterior</a>'; }

    if ($start > 1) { echo '<a href="?' . $current_query_base . '&page=1">1</a> <span>...</span>'; }
    
    for ($i = $start; $i <= $end; $i++) {
        $link = '?' . $current_query_base . '&page=' . $i;
        if ($i == $page) {
            echo '<span class="active">' . $i . '</span>';
        } else {
            echo '<a href="' . $link . '">' . $i . '</a>';
        }
    }

    if ($end < $totalPages) { echo '<span>...</span> <a href="?' . $current_query_base . '&page=' . $totalPages . '">' . $totalPages . '</a>'; }

    if ($page < $totalPages) { echo '<a href="?' . $current_query_base . '&page=' . ($page + 1) . '">Próxima &raquo;</a>'; }
    ?>
</div>

<?php
// -------------------------------------------------------------------
// INCLUSÃO DO RODAPÉ (footer.php) - FIM DA SAÍDA HTML
// -------------------------------------------------------------------
require_once 'footer.php';
?>