<?php
// =================================================================
// 1. INCLUSÃO DO ARQUIVO DE CONEXÃO E VARIÁVEIS INICIAIS
// =================================================================
// Assumindo que 'conexao.php' e 'header.php' e 'footer.php' existem.
include 'conexao.php'; 

$pageTitle = "Dashboard e Lista de Colaboradores";
$pagina_atual = 'dashboard_colaboradores.php';

// Variáveis de filtro
// O uso do Null Coalescing Operator (??) requer PHP 7.0+
$filtro_setor = (int) ($_GET['setor_id'] ?? 0);
$filtro_funcao = (int) ($_GET['funcao_id'] ?? 0);
$filtro_status = isset($_GET['status']) ? (int) $_GET['status'] : -1; // -1 significa "Todos"

// Variável para mensagens de status
$mensagem_status = '';
$mensagem_status_ultimos = '';

// =================================================================
// 2. FUNÇÃO PARA BUSCAR DADOS DE REFERÊNCIA (Setores e Funções)
// =================================================================
/**
 * Busca dados de tabelas de referência (Setores, Funções, etc.).
 * @param mysqli $conn Conexão com o banco de dados.
 * @param string $tabela Nome da tabela de referência.
 * @param string $id_coluna Nome da coluna ID.
 * @param string $desc_coluna Nome da coluna de descrição.
 * @return array Array associativo com ID => Descrição.
 */
function buscar_referencia($conn, $tabela, $id_coluna, $desc_coluna) {
    $dados = [];
    $sql = "SELECT {$id_coluna}, {$desc_coluna} FROM {$tabela} ORDER BY {$desc_coluna} ASC";
    // É seguro usar query() aqui, pois não há entrada do usuário
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dados[$row[$id_coluna]] = $row[$desc_coluna];
        }
    }
    return $dados;
}

$setores = buscar_referencia($conn, 'setores', 'id_setor', 'nome_setor');
$funcoes = buscar_referencia($conn, 'funcoes', 'id_funcao', 'nome_funcao');
$situacoes = buscar_referencia($conn, 'situacao', 'id_situacao', 'desc_situacao');

// =================================================================
// 2.5. CÁLCULO DE KPIS GERAIS (Total Ativos e Inativos)
// =================================================================

$total_ativos = 0;
$total_inativos = 0;

// Consulta para Total de Colaboradores ATIVOS (status = 1)
$sql_ativos = "SELECT COUNT(*) AS total FROM colaboradores WHERE status = 1";
$result_ativos = $conn->query($sql_ativos);
if ($result_ativos) {
    $row = $result_ativos->fetch_assoc();
    $total_ativos = (int) $row['total'];
}

// Consulta para Total de Colaboradores INATIVOS (status = 0)
$sql_inativos = "SELECT COUNT(*) AS total FROM colaboradores WHERE status = 0";
$result_inativos = $conn->query($sql_inativos);
if ($result_inativos) {
    $row = $result_inativos->fetch_assoc();
    $total_inativos = (int) $row['total'];
}


// =================================================================
// 3. CONSULTA DOS 10 ÚLTIMOS REGISTROS POR DATA DE ADMISSÃO (Edição Rápida)
// =================================================================

$sql_ultimos = "SELECT 
            c.id_colaborador, 
            c.nome, 
            c.matricula, 
            s.nome_setor,
            f.nome_funcao,
            sit.desc_situacao,
            c.status,
            DATE_FORMAT(c.data_admissao, '%d/%m/%Y') AS data_admissao_formatada
        FROM colaboradores c
        LEFT JOIN setores s ON c.setor_id = s.id_setor
        LEFT JOIN funcoes f ON c.funcao_id = f.id_funcao
        LEFT JOIN situacao sit ON c.situacao_id = sit.id_situacao
        ORDER BY c.data_admissao DESC 
        LIMIT 10"; 

$ultimos_colaboradores = [];
$result_ultimos = $conn->query($sql_ultimos);

if ($result_ultimos) {
    while ($row = $result_ultimos->fetch_assoc()) {
        $ultimos_colaboradores[] = $row;
    }
} else {
    $mensagem_status_ultimos = "<div class='alert alert-danger'>
                                ❌ Erro ao buscar últimos colaboradores: " . htmlspecialchars($conn->error) . "
                                <br>Verifique a sintaxe SQL (colunas/tabelas) em 'Últimos 10 Colaboradores'.
                              </div>";
}


$sql = "SELECT 
            c.id_colaborador, 
            c.nome, 
            c.matricula, 
            DATE_FORMAT(c.data_admissao, '%d/%m/%Y') AS data_admissao_formatada,
            s.nome_setor,
            f.nome_funcao,
            sit.desc_situacao,
            c.status
        FROM colaboradores c
        LEFT JOIN setores s ON c.setor_id = s.id_setor
        LEFT JOIN funcoes f ON c.funcao_id = f.id_funcao
        LEFT JOIN situacao sit ON c.situacao_id = sit.id_situacao
        WHERE 1=1"; 

$params = [];
$types = '';
$total_colaboradores = 0; 

// Adicionar filtro por Setor
if ($filtro_setor > 0) {
    $sql .= " AND c.setor_id = ?";
    $params[] = $filtro_setor;
    $types .= 'i';
}

// Adicionar filtro por Função
if ($filtro_funcao > 0) {
    $sql .= " AND c.funcao_id = ?";
    $params[] = $filtro_funcao;
    $types .= 'i';
}

// Adicionar filtro por Status (0=Inativo, 1=Ativo, -1=Todos)
if ($filtro_status !== -1) {
    $sql .= " AND c.status = ?";
    $params[] = $filtro_status;
    $types .= 'i';
}

$sql .= " ORDER BY c.nome ASC";

$colaboradores = [];

if ($stmt = $conn->prepare($sql)) {
    
    // Contagem Total (KPI de Filtro)
    $sql_count = "SELECT COUNT(*) FROM colaboradores WHERE 1=1";
    
    // Adiciona as mesmas condições WHERE para a contagem
    if ($filtro_setor > 0) $sql_count .= " AND setor_id = ?";
    if ($filtro_funcao > 0) $sql_count .= " AND funcao_id = ?";
    if ($filtro_status !== -1) $sql_count .= " AND status = ?";

    $stmt_count = $conn->prepare($sql_count);
    
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params); 
    }
    
    $stmt_count->execute();
    $stmt_count->bind_result($total_colaboradores);
    $stmt_count->fetch();
    $stmt_count->close();
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $colaboradores[] = $row;
        }
    }
    $stmt->close();
} else {
    $mensagem_status = "<div class='alert alert-danger'>❌ Erro ao preparar consulta (Filtros): " . htmlspecialchars($conn->error) . "</div>";
}

require_once 'header.php'; 

?>
    <main class="container">
        
        <h2 class="section-header"><?php echo $pageTitle; ?></h2>

        <?php echo $mensagem_status; ?>

        <div class="kpi-grid">
            
            <div class="kpi-card">
                <div class="kpi-value" style="color: #007bff;"><?php echo number_format(($total_ativos + $total_inativos), 0, ',', '.'); ?></div>
                <div class="kpi-label">Total Geral de Colaboradores</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value" style="color: #28a745;"><?php echo number_format($total_ativos, 0, ',', '.'); ?></div>
                <div class="kpi-label">Colaboradores Ativos</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value" style="color: #dc3545;"><?php echo number_format($total_inativos, 0, ',', '.'); ?></div>
                <div class="kpi-label">Colaboradores Inativos</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo number_format($total_colaboradores, 0, ',', '.'); ?></div>
                <div class="kpi-label">Colaboradores Encontrados (Filtro)</div>
            </div>
        </div>


        <h3>➡️ Últimos 10 Colaboradores Admitidos (Edição Rápida)</h3>
        
        <?php echo $mensagem_status_ultimos; ?>

        <?php if (count($ultimos_colaboradores) > 0): ?>
            <table class="data-table recent-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 25%;">Nome</th>
                        <th style="width: 10%;">Matrícula</th>
                        <th style="width: 15%;">Setor</th>
                        <th style="width: 15%;">Função</th>
                        <th style="width: 15%;">Situação</th>
                        <th style="width: 10%;">Data Admissão</th>
                        <th style="width: 5%;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos_colaboradores as $colab): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($colab['id_colaborador']); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome']); ?></td>
                            <td><?php echo htmlspecialchars($colab['matricula'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome_setor'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome_funcao'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['desc_situacao'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['data_admissao_formatada']); ?></td>
                            <td>
                                <a href="editar_colaborador.php?id=<?php echo $colab['id_colaborador']; ?>" class="btn btn-primary btn-small">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php if (empty($mensagem_status_ultimos)): ?>
                <div class="alert alert-info">
                    Nenhum colaborador encontrado no banco de dados.
               </div>
            <?php endif; ?>
        <?php endif; ?>

        <h3>Filtrar e Buscar Colaboradores</h3>

        <form action="<?php echo $pagina_atual; ?>" method="GET" class="filter-form">
            
            <div class="filter-group">
                <label for="setor_id">Filtrar por Setor</label>
                <select id="setor_id" name="setor_id">
                    <option value="0">Todos os Setores</option>
                    <?php foreach ($setores as $id => $nome): ?>
                        <option value="<?php echo $id; ?>" 
                            <?php echo ($id == $filtro_setor ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="funcao_id">Filtrar por Função</label>
                <select id="funcao_id" name="funcao_id">
                    <option value="0">Todas as Funções</option>
                    <?php foreach ($funcoes as $id => $nome): ?>
                        <option value="<?php echo $id; ?>" 
                            <?php echo ($id == $filtro_funcao ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">Filtrar por Status</label>
                <select id="status" name="status">
                    <option value="-1">Todos os Status</option>
                    <option value="1" <?php echo ($filtro_status === 1 ? 'selected' : ''); ?>>Ativo</option>
                    <option value="0" <?php echo ($filtro_status === 0 ? 'selected' : ''); ?>>Inativo</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                <a href="<?php echo $pagina_atual; ?>" class="btn btn-secondary">Limpar Filtros</a>
                <a href="cadastrar_colaborador.php" class="btn btn-success">+ Novo Colaborador</a>
            </div>
        </form>

        <h3>Resultado da Busca (Total: <?php echo $total_colaboradores; ?>)</h3>
        
        <?php if (count($colaboradores) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 25%;">Nome</th>
                        <th style="width: 10%;">Matrícula</th>
                        <th style="width: 10%;">Admissão</th>
                        <th style="width: 15%;">Setor</th>
                        <th style="width: 15%;">Função</th>
                        <th style="width: 10%;">Situação</th>
                        <th style="width: 5%;">Status</th>
                        <th style="width: 5%;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colaboradores as $colab): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($colab['id_colaborador']); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome']); ?></td>
                            <td><?php echo htmlspecialchars($colab['matricula'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['data_admissao_formatada']); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome_setor'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['nome_funcao'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($colab['desc_situacao'] ?? 'N/D'); ?></td>
                            <td>
                                <span class="<?php echo ($colab['status'] == 1 ? 'status-ativo' : 'status-inativo'); ?>">
                                    <?php echo ($colab['status'] == 1 ? 'ATIVO' : 'INATIVO'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="alterar_colaborador.php?id=<?php echo $colab['id_colaborador']; ?>" title="Editar">
                                    ⚙️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                Não foram encontrados colaboradores com os filtros aplicados.
            </div>
        <?php endif; ?>
    
    </main>
    
    <?php require_once 'footer.php'; ?>
