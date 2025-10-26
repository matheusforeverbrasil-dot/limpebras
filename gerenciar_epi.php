<?php
// =======================================================
// gerenciar_epi.php - Cadastro, Edição, Busca e Listagem de EPIs
// =======================================================

$pageTitle = "Gerenciamento de EPIs";
require_once 'header.php'; // Inclui a conexão e o cabeçalho HTML

// Verifica a conexão (proteção extra)
if (!$conexao_status || !isset($conn) || !$conn->ping()) {
    echo '<div class="alert-error"><strong>Falha Crítica:</strong> Não foi possível conectar ao banco de dados.</div>';
    require_once 'footer.php';
    exit;
}

$mensagem_status = '';

// =======================================================
// 1. LÓGICA DE PROCESSAMENTO (CRUD - Exemplo: Exclusão)
// A lógica de INSERT e UPDATE seria implementada aqui, 
// mas é omitida para manter o foco na listagem e busca.
// =======================================================

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_epi_delete = (int)$_GET['id'];
    
    // ATENÇÃO: Se houver dependências (Foreign Keys) em entregas_epi ou outras, a exclusão irá falhar!
    // Na prática, você deve verificar as dependências ou usar uma exclusão lógica.
    
    $sql_delete = "DELETE FROM epis WHERE id_epi = ?";
    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("i", $id_epi_delete);
        if ($stmt->execute()) {
            $mensagem_status = '<div class="alert-success">EPI excluído com sucesso (ID: ' . $id_epi_delete . ').</div>';
        } else {
            // Este erro é comum se houver dados em 'entregas_epi' referenciando este EPI
            $mensagem_status = '<div class="alert-error">Erro ao excluir o EPI: O item possui registros de saídas/entregas e não pode ser deletado. ' . htmlspecialchars($conn->error) . '</div>';
        }
        $stmt->close();
    }
}


// =======================================================
// 2. LÓGICA DE LISTAGEM E FILTRO
// =======================================================

$filtro_pesquisa = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql_select = "SELECT id_epi, nome_epi, ca, validade, estoque, qnt_minima FROM epis ";
$where_clauses = [];
$params = [];
$types = '';

// Filtro por termo de busca (Nome ou CA)
if (!empty($filtro_pesquisa)) {
    $where_clauses[] = "(nome_epi LIKE ? OR ca LIKE ?)";
    $params[] = "%$filtro_pesquisa%";
    $params[] = "%$filtro_pesquisa%";
    $types .= 'ss';
}

// Filtro por Status de Estoque/Validade
if ($filtro_status == 'baixo') {
    $where_clauses[] = "estoque <= qnt_minima AND estoque > 0";
} elseif ($filtro_status == 'zero') {
    $where_clauses[] = "estoque <= 0";
} elseif ($filtro_status == 'vencido') {
    $where_clauses[] = "validade < CURDATE()";
} elseif ($filtro_status == 'proximo') {
    $where_clauses[] = "validade <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND validade >= CURDATE()";
}

if (!empty($where_clauses)) {
    $sql_select .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_select .= " ORDER BY validade ASC, nome_epi ASC";

$stmt = $conn->prepare($sql_select);
if (!empty($params)) {
    // Usando call_user_func_array para bind_param dinâmico
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
}

$stmt->execute();
$result_epis = $stmt->get_result();

// =======================================================
// 3. HTML DA PÁGINA
// =======================================================
?>

<div class="actions-bar">
    <a href="dashboard_epi.php" class="btn btn-secondary">
        <i class="fas fa-chart-bar"></i> Voltar ao Dashboard
    </a>
    <button class="btn btn-primary" onclick="window.location.href='cadastro_epi.php'">
        <i class="fas fa-plus"></i> Cadastrar Novo EPI
    </button>
</div>

<h2>Gerenciamento de Estoque de EPIs</h2>

<?= $mensagem_status ?>

<div class="filter-area card-area">
    <form method="GET" class="search-form-full">
        <label for="autocomplete-epi">Buscar EPI (Nome ou CA):</label>
        <div class="input-group">
            <input type="text" id="autocomplete-epi" name="q" placeholder="Digite o nome ou CA para buscar..." 
                   value="<?= htmlspecialchars($filtro_pesquisa) ?>" class="form-control-full">
            <button type="submit" class="btn btn-primary-alt"><i class="fas fa-search"></i> Filtrar</button>
        </div>
        
        <label for="filter-status" class="mt-3">Filtro Rápido por Status:</label>
        <select name="status" id="filter-status" class="form-control-inline" onchange="this.form.submit()">
            <option value="all" <?= $filtro_status == 'all' ? 'selected' : '' ?>>Todos os Itens</option>
            <option value="baixo" <?= $filtro_status == 'baixo' ? 'selected' : '' ?>>Abaixo do Mínimo</option>
            <option value="zero" <?= $filtro_status == 'zero' ? 'selected' : '' ?>>Estoque Zero</option>
            <option value="proximo" <?= $filtro_status == 'proximo' ? 'selected' : '' ?>>CA Vencendo (90 dias)</option>
            <option value="vencido" <?= $filtro_status == 'vencido' ? 'selected' : '' ?>>CA Vencido</option>
        </select>
        
        <?php if (!empty($filtro_pesquisa) || $filtro_status != 'all'): ?>
            <a href="gerenciar_epi.php" class="btn btn-secondary btn-clear-filter">Limpar Filtros</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome do EPI</th>
                <th>CA (Certif. Aprovação)</th>
                <th>Validade CA</th>
                <th>Estoque Atual</th>
                <th>Estoque Mínimo</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result_epis->num_rows > 0): ?>
                <?php while ($epi = $result_epis->fetch_assoc()): 
                    $status_class = '';
                    $status_text = 'Estoque OK';

                    // Lógica de Status (Deve ser a mesma do Dashboard)
                    if (strtotime($epi['validade']) < time()) {
                        $status_class = 'status-vencido';
                        $status_text = 'CA VENCIDO';
                    } elseif ($epi['estoque'] <= 0) {
                        $status_class = 'status-critico';
                        $status_text = 'ESTOQUE ZERO';
                    } elseif ($epi['estoque'] <= $epi['qnt_minima']) {
                        $status_class = 'status-alerta';
                        $status_text = 'ABAIXO DO MÍNIMO';
                    } elseif (strtotime($epi['validade']) < strtotime('+90 days')) {
                        $status_class = 'status-proximo-venc';
                        $status_text = 'CA PROX. VENC.';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($epi['id_epi']) ?></td>
                    <td><?= htmlspecialchars($epi['nome_epi']) ?></td>
                    <td><?= htmlspecialchars($epi['ca']) ?></td>
                    <td><?= date('d/m/Y', strtotime($epi['validade'])) ?></td>
                    <td><?= number_format($epi['estoque'], 0, ',', '.') ?></td>
                    <td><?= number_format($epi['qnt_minima'], 0, ',', '.') ?></td>
                    <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                    <td class="action-column">
                        <a href="editar_epi.php?id=<?= $epi['id_epi'] ?>" class="btn btn-sm btn-edit" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="movimentar_epi.php?id=<?= $epi['id_epi'] ?>" class="btn btn-sm btn-movement" title="Dar Saída/Entrada">
                            <i class="fas fa-exchange-alt"></i>
                        </a>
                        <a href="?action=delete&id=<?= $epi['id_epi'] ?>" 
                           onclick="return confirm('ATENÇÃO: Deseja realmente excluir o EPI: <?= htmlspecialchars($epi['nome_epi']) ?>? Esta ação é IRREVERSÍVEL e pode falhar se houver dependências.')" 
                           class="btn btn-sm btn-delete" title="Excluir">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">Nenhum EPI encontrado com os filtros aplicados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
$(function() {
    // Inicializa o Autocomplete
    $("#autocomplete-epi").autocomplete({
        // Fonte dos dados: O arquivo dedicado na raiz que retorna o JSON
        source: "epi_search.php", 
        minLength: 2,
        select: function(event, ui) {
            // Quando um item é selecionado, o formulário é enviado para filtrar
            // O valor selecionado (nome do EPI) é colocado no campo de busca 'q'
            $('#autocomplete-epi').val(ui.item.value);
            $('form.search-form-full').submit(); 
        }
    });
});
</script>

<?php
$stmt->close();
$conn->close();
require_once 'footer.php';
?>