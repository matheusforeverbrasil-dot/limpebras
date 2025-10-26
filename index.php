<?php
// =================================================================
// 1. INCLUSÃO DA CONEXÃO E VARIÁVEIS INICIAIS
// =================================================================

// Inclui o arquivo que contém as configurações e a variável $conn (objeto mysqli)
require_once 'conexao.php'; 

// Inicializa as contagens para o Status de Treinamentos
$kpi_contagens = [
    'vencido' => ['titulo' => 'Vencidos', 'valor' => 0, 'classe' => 'red-kpi', 'link_param' => 'vencido', 'detalhe' => 'Ver Pendências &rarr;'],
    'vencendo' => ['titulo' => 'Vencendo (30 dias)', 'valor' => 0, 'classe' => 'yellow-kpi', 'link_param' => 'vencendo', 'detalhe' => 'Ver Alerta &rarr;'],
    'em_dia' => ['titulo' => 'Em Dia', 'valor' => 0, 'classe' => 'green-kpi', 'link_param' => 'em_dia', 'detalhe' => 'Ver Conformidade &rarr;'],
];

// Inicializa as contagens para o Status de Vencimento de CA (EPI)
$kpi_epi_ca = [
    'ca_vencido' => ['titulo' => 'CAs Vencidos', 'valor' => 0, 'classe' => 'red-kpi', 'link_param' => 'ca_vencido', 'detalhe' => 'Ver Pendências &rarr;'],
    'ca_vencendo' => ['titulo' => 'CAs Vencendo (90 dias)', 'valor' => 0, 'classe' => 'yellow-kpi', 'link_param' => 'ca_vencendo', 'detalhe' => 'Ver Alerta &rarr;'],
    'ca_em_dia' => ['titulo' => 'CAs Em Dia', 'valor' => 0, 'classe' => 'green-kpi', 'link_param' => 'ca_em_dia', 'detalhe' => 'Ver Conformidade &rarr;'],
];

// Simula dados de outras tabelas para os cards de contagem
$outros_cards = [
    'colaboradores' => ['tabela' => 'colaboradores', 'titulo' => 'Total de Colaboradores', 'cor' => '#17a2b8', 'valor' => 0, 'icone' => 'fas fa-users', 'link' => 'dashboard_colaboradores.php', 'classe' => 'color-info'],
    'riscos' => ['tabela' => 'riscos', 'titulo' => 'Riscos Mapeados', 'cor' => '#9b68dd88', 'valor' => 0, 'icone' => 'fas fa-biohazard', 'link' => 'riscos_dashboard.php', 'classe' => 'color-danger'],
    'epi' => ['tabela' => 'epis', 'titulo' => 'EPIs/CAs Cadastrados', 'cor' => '#6f42c1', 'valor' => 0, 'icone' => 'fas fa-mask', 'link' => 'epi_dashboard.php', 'classe' => 'color-purple'], 
];

// Definimos as classes e cores para os cards de lista/últimos lançamentos
$list_cards_config = [
    'admissoes' => ['color' => '#6f42c1', 'icon' => 'fas fa-user-plus'],
    'atestados' => ['color' => '#3855daff', 'icon' => 'fas fa-clinic-medical'],
    'inspecoes' => ['color' => '#4edab0ff', 'icon' => 'fas fa-file-signature'],
];

// Definimos as classes e cores para a navegação rápida
$nav_links_config = [
    'colaboradores' => ['color_class' => 'nav-color-info', 'icon' => 'fas fa-user-friends'],
    'treinamentos' => ['color_class' => 'nav-color-success', 'icon' => 'fas fa-chalkboard-teacher'],
    'epis' => ['color_class' => 'nav-color-purple', 'icon' => 'fas fa-box-open'],
    'atestados' => ['color_class' => 'nav-color-orange', 'icon' => 'fas fa-stethoscope'],
    'registrar' => ['color_class' => 'nav-color-danger', 'icon' => 'fas fa-plus-circle'],
    'relatorios' => ['color_class' => 'nav-color-info', 'icon' => 'fas fa-file-export'],
];


$conexao_status = true;
$conexao_erro = '';
$ultimos_colaboradores = [];
$ultimos_atestados = [];

// Checa o status da conexão que deve ter sido estabelecida em 'conexao.php'
if (isset($conn) && $conn->connect_error) {
    $conexao_status = false;
    $conexao_erro = "Erro de Conexão: " . htmlspecialchars($conn->connect_error);
}

// Título da página
$pageTitle = "Dashboard Principal - SST";

// =================================================================
// 2. FUNÇÕES AUXILIARES
// =================================================================

function formatar_colaborador($nome, $matricula, $limite = 20) {
    $nome = htmlspecialchars($nome);
    $matricula = htmlspecialchars($matricula);
    
    if (mb_strlen($nome) > $limite) {
        $nome_truncado = mb_substr($nome, 0, $limite) . '...';
    } else {
        $nome_truncado = $nome;
    }
    
    if (!empty($matricula)) {
        return $nome_truncado . ' - ' . $matricula;
    }
    return $nome_truncado;
}

function fetch_kpi_count($conn, $sql, $types = null, $params = []) {
    // Adicionado isset para evitar erro se $conn não for definido
    if (!isset($conn) || $conn->connect_error) { 
        return 0;
    }

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        global $conexao_status, $conexao_erro;
        $conexao_status = false;
        $conexao_erro = "Erro de Query: " . htmlspecialchars($conn->error) . " | SQL: " . htmlspecialchars($sql);
        return 0; 
    }

    if ($types && !empty($params)) {
        if (strlen($types) !== count($params)) {
             $stmt->close();
             return 0;
        }
        // Usando call_user_func_array para bind_param no PHP < 5.6
        // ou a sintaxe ...$params em versões recentes
        $stmt->bind_param($types, ...$params); 
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $stmt->close();
            return (int) $row['count'];
        }
    }
    
    $stmt->close();
    return 0;
}

// =================================================================
// 3. LÓGICA DE CONSULTA DE DADOS
// =================================================================
if ($conexao_status && isset($conn)) {
    $conn->set_charset("utf8mb4");
    $current_date = date('Y-m-d');
    $date_30_days = date('Y-m-d', strtotime('+30 days'));
    $date_90_days = date('Y-m-d', strtotime('+90 days'));

    // Treinamentos
    $sql_vencido = "SELECT COUNT(*) AS count FROM treinamentos WHERE data_vencimento < ?";
    $kpi_contagens['vencido']['valor'] = fetch_kpi_count($conn, $sql_vencido, 's', [$current_date]);

    $sql_vencendo = "SELECT COUNT(*) AS count FROM treinamentos WHERE data_vencimento BETWEEN ? AND ?";
    $kpi_contagens['vencendo']['valor'] = fetch_kpi_count($conn, $sql_vencendo, 'ss', [$current_date, $date_30_days]);

    $total_treinamentos = fetch_kpi_count($conn, "SELECT COUNT(*) AS count FROM treinamentos");
    $vencidos_e_vencendo_treinamentos = $kpi_contagens['vencido']['valor'] + $kpi_contagens['vencendo']['valor'];
    $kpi_contagens['em_dia']['valor'] = max(0, $total_treinamentos - $vencidos_e_vencendo_treinamentos);

    // CAs
    $sql_ca_vencido = "SELECT COUNT(*) AS count FROM epis WHERE validade < ?";
    $kpi_epi_ca['ca_vencido']['valor'] = fetch_kpi_count($conn, $sql_ca_vencido, 's', [$current_date]);

    $sql_ca_vencendo = "SELECT COUNT(*) AS count FROM epis WHERE validade BETWEEN ? AND ?";
    $kpi_epi_ca['ca_vencendo']['valor'] = fetch_kpi_count($conn, $sql_ca_vencendo, 'ss', [$current_date, $date_90_days]);

    $total_cas = fetch_kpi_count($conn, "SELECT COUNT(*) AS count FROM epis");
    $vencidos_e_vencendo_ca = $kpi_epi_ca['ca_vencido']['valor'] + $kpi_epi_ca['ca_vencendo']['valor'];
    $kpi_epi_ca['ca_em_dia']['valor'] = max(0, $total_cas - $vencidos_e_vencendo_ca);

    // Outros Cards
    foreach ($outros_cards as $key => $card) {
        $tabela_ajustada = $card['tabela']; 
        $sql_card = "SELECT COUNT(*) AS count FROM `{$tabela_ajustada}`"; 
        $outros_cards[$key]['valor'] = fetch_kpi_count($conn, $sql_card);
    }
    
    // Últimos Colaboradores
    $sql_ultimos_colab = "
        SELECT c.nome, c.matricula, DATE_FORMAT(c.data_admissao, '%d/%m/%Y') AS data_admissao_formatada, s.nome_setor, f.nome_funcao
        FROM colaboradores c 
        LEFT JOIN setores s ON c.setor_id = s.id_setor
        LEFT JOIN funcoes f ON c.funcao_id = f.id_funcao
        ORDER BY c.data_admissao DESC 
        LIMIT 5";
            
    $result_ultimos_colab = $conn->query($sql_ultimos_colab);

    if ($result_ultimos_colab === false) {
        $conexao_status = false;
        $conexao_erro = "Erro na Consulta de Admissões: " . htmlspecialchars($conn->error);
    } else {
        while ($row = $result_ultimos_colab->fetch_assoc()) {
            $ultimos_colaboradores[] = $row;
        }
    }

    // Últimos Atestados
    $sql_ultimos_atestados = "
        SELECT c.nome AS colaborador_nome, DATE_FORMAT(a.data_atestado, '%d/%m') AS data_formatada
        FROM atestados a 
        LEFT JOIN colaboradores c ON a.colaborador_id = c.id_colaborador 
        ORDER BY a.data_atestado DESC 
        LIMIT 5";
            
    $result_ultimos_atestados = $conn->query($sql_ultimos_atestados);
    if ($result_ultimos_atestados) {
        while ($row = $result_ultimos_atestados->fetch_assoc()) {
            $ultimos_atestados[] = $row;
        }
    }
}

// Título da página (Reafirmado aqui para ser usado no header.php)
$pageTitle = "Dashboard Principal - SST";


// 4. INCLUSÃO DO CABEÇALHO
require_once 'header.php'; 
?>

<?php if (!$conexao_status): ?>
    <div class="alert-error">
        <strong>⚠️ Erro no Banco de Dados:</strong> Os dados exibidos podem estar incompletos ou zerados devido a uma falha. <br><?php echo $conexao_erro; ?>
    </div>
<?php endif; ?>

<section class="kpi-section">
    <h2><i class="fas fa-chart-pie"></i> Visualização de Status</h2>
    
    <div class="kpi-grid">
        
        <div class="chart-card">
            <h3>Status de Treinamentos</h3>
            <div class="chart-container">
                <canvas id="treinamentosChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3>Status de CA (EPI)</h3>
            <div class="chart-container">
                <canvas id="caChart"></canvas>
            </div>
        </div>
    </div>
</section>


<section class="data-cards-section">
    <h2><i class="fas fa-tachometer-alt"></i> Resumo Geral do Sistema & Lançamentos Recentes</h2>
    
    <div class="data-cards-grid"> 
        
        <?php 
        // Cards de Contagem Simples
        $cards_simples = [$outros_cards['colaboradores'], $outros_cards['riscos'], $outros_cards['epi']];
        foreach ($cards_simples as $card): 
        ?>
        <div class="data-card" style="border-left-color: <?php echo $card['cor']; ?>;">
            <p style="color: <?php echo $card['cor']; ?>;">
                <i class="<?php echo $card['icone']; ?> icon"></i>
                <?php echo htmlspecialchars($card['titulo']); ?>
            </p>
            <p class="data-value"><?php echo $card['valor']; ?></p>
            <a href="<?php echo htmlspecialchars($card['link']); ?>" class="kpi-link" style="color: <?php echo $card['cor']; ?>;">
                Gerenciar &rarr;
            </a>
        </div>
        <?php endforeach; ?>
        
        <div class="list-card admissoes">
            <h3 style="color: <?php echo $list_cards_config['admissoes']['color']; ?>;">
                <i class="<?php echo $list_cards_config['admissoes']['icon']; ?>"></i> Últimas Admissões
            </h3>
            <?php if (!empty($ultimos_colaboradores)): ?>
                <ul>
                    <?php foreach ($ultimos_colaboradores as $colab): ?>
                        <li>
                            <span class="colab-main">
                                <?php echo formatar_colaborador($colab['nome'], $colab['matricula']); ?>
                            </span>
                            <span class="colab-details">
                                Setor: <?php echo htmlspecialchars($colab['nome_setor'] ?? 'N/A'); ?> | 
                                Função: <?php echo htmlspecialchars($colab['nome_funcao'] ?? 'N/A'); ?>
                            </span>
                            <span class="colab-details date">
                                Admissão: <?php echo htmlspecialchars($colab['data_admissao_formatada']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="no-data">Nenhum colaborador registrado recentemente.</p>
            <?php endif; ?>
            <a href="dashboard_colaboradores.php" class="kpi-link" style="color: <?php echo $list_cards_config['admissoes']['color']; ?>;">
                Ver todos &rarr;
            </a>
        </div>
        
        <div class="list-card atestados">
            <h3 style="color: <?php echo $list_cards_config['atestados']['color']; ?>;">
                <i class="<?php echo $list_cards_config['atestados']['icon']; ?>"></i> Últimos 5 Atestados
            </h3>
            <?php if (!empty($ultimos_atestados)): ?>
                <ul>
                    <?php foreach ($ultimos_atestados as $atestado): ?>
                        <li>
                            <?php echo formatar_colaborador($atestado['colaborador_nome'], ''); ?>
                            <span class="date"><?php echo htmlspecialchars($atestado['data_formatada']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="no-data">Nenhum atestado encontrado. <br> (Verifique a tabela 'atestados').</p>
            <?php endif; ?>
            <a href="atestados_dashboard.php" class="kpi-link" style="color: <?php echo $list_cards_config['atestados']['color']; ?>;">
                Gerenciar Atestados &rarr;
            </a>
        </div>
        
        <div class="data-card" style="border-left-color: <?php echo $list_cards_config['inspecoes']['color']; ?>;">
            <p style="color: <?php echo $list_cards_config['inspecoes']['color']; ?>;">
                <i class="<?php echo $list_cards_config['inspecoes']['icon']; ?> icon"></i> Inspeções Programadas
            </p>
            <p class="data-value">4</p>
            <a href="inspecoes.php" class="kpi-link" style="color: <?php echo $list_cards_config['inspecoes']['color']; ?>;">
                Ver Lista &rarr;
            </a>
        </div>

    </div>
</section>

<section class="navigation-section">
    <h2><i class="fas fa-route"></i> Navegação Rápida</h2>
    <div class="nav-links-grid">
        
        <a href="dashboard_colaboradores.php" class="nav-link-item <?php echo $nav_links_config['colaboradores']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['colaboradores']['icon']; ?>"></i> Cadastro de Colaboradores</h3>
            <p>Adicionar ou editar dados de funcionários.</p>
        </a>
        
        <a href="dashboard_treinamento.php" class="nav-link-item <?php echo $nav_links_config['treinamentos']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['treinamentos']['icon']; ?>"></i> Controle de Treinamentos</h3>
            <p>Filtros, paginação e status de vencimento.</p>
        </a>
        
        <a href="epi_dashboard.php" class="nav-link-item <?php echo $nav_links_config['epis']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['epis']['icon']; ?>"></i> Gestão de EPIs</h3>
            <p>Controle de entrega e validade de CAs.</p>
        </a>
        
        <a href="atestados.php" class="nav-link-item <?php echo $nav_links_config['atestados']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['atestados']['icon']; ?>"></i> Gerenciar Atestados</h3>
            <p>Lançar, buscar e auditar registros de saúde.</p>
        </a>
        
        <a href="create_treinamento.php" class="nav-link-item <?php echo $nav_links_config['registrar']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['registrar']['icon']; ?>"></i> Registrar Treinamento</h3>
            <p>Lançar novos treinamentos e datas de vencimento.</p>
        </a>
        
        <a href="relatorios.php" class="nav-link-item <?php echo $nav_links_config['relatorios']['color_class']; ?>">
            <h3><i class="<?php echo $nav_links_config['relatorios']['icon']; ?>"></i> Relatórios e Exportações</h3>
            <p>Gerar relatórios de conformidade e documentos.</p>
        </a>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
    // =========================================================
    // === CÓDIGO PARA OS GRÁFICOS (Chart.js) ===
    // =========================================================

    function createChart(canvasId, title, labels, data, type = 'doughnut') {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            console.error(`Canvas '${canvasId}' ou Chart.js não encontrado.`);
            return;
        }
        
        // Cores padronizadas: Danger (Vencido), Warning (Vencendo), Success (Em Dia)
        const BACKGROUND_COLORS = ['#dc3545', '#ffc107', '#28a745'];

        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: title,
                    data: data,
                    backgroundColor: BACKGROUND_COLORS, 
                    borderColor: 'rgba(255, 255, 255, 0.8)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    title: {
                        display: false,
                    }
                },
                layout: {
                    padding: 0
                }
            }
        });
    }

    // Dados do PHP (Transferidos para JavaScript)
    const kpi_treinamentos = <?php echo json_encode($kpi_contagens); ?>;
    const kpi_epi_ca = <?php echo json_encode($kpi_epi_ca); ?>;

    // 1. Gráfico de Treinamentos
    const treinamentoLabels = [
        kpi_treinamentos.vencido.titulo, 
        kpi_treinamentos.vencendo.titulo, 
        kpi_treinamentos.em_dia.titulo
    ];
    const treinamentoData = [
        kpi_treinamentos.vencido.valor, 
        kpi_treinamentos.vencendo.valor, 
        kpi_treinamentos.em_dia.valor
    ];

    // 2. Gráfico de CA (EPI)
    const caLabels = [
        kpi_epi_ca.ca_vencido.titulo, 
        kpi_epi_ca.ca_vencendo.titulo, 
        kpi_epi_ca.ca_em_dia.titulo
    ];
    const caData = [
        kpi_epi_ca.ca_vencido.valor, 
        kpi_epi_ca.ca_vencendo.valor, 
        kpi_epi_ca.ca_em_dia.valor
    ];


    // Inicialização dos gráficos quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        createChart('treinamentosChart', 'Status de Treinamentos', treinamentoLabels, treinamentoData, 'doughnut');
        createChart('caChart', 'Status de CA (EPI)', caLabels, caData, 'doughnut');
    });

</script>

<?php require_once 'footer.php'; ?>