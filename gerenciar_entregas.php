<?php
// =================================================================
// gerenciar_entregas.php - GERE. DE EPI (Busca, Relatório, PDF, CSV)
// CÓDIGO CORRIGIDO: Coluna 'setor_id' utilizada no JOIN da tabela 'colaboradores'.
// =================================================================

// 🚨 1. CONFIGURAÇÕES E CONEXÃO
// -----------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst'); 

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conexao_status = true;

if ($conn->connect_error) {
    $conexao_status = false;
    $conexao_erro = "Erro de Conexão: " . $conn->connect_error;
    if (isset($_GET['action'])) {
        die("Falha na conexão com o Banco de Dados: " . $conexao_erro);
    }
}
if ($conexao_status) {
    $conn->set_charset("utf8mb4");
}

// -----------------------------------------------------------------
// 🚨 2. FUNÇÕES DE SUPORTE
// -----------------------------------------------------------------

/**
 * Executa uma consulta que busca o cabeçalho e os itens de uma ficha de entrega.
 * @param mysqli $conn Conexão ativa com o banco.
 * @param string $numero_ordem Número de ordem da ficha.
 * @return array|false Dados da ficha ou false se não for encontrada.
 */
function buscarDadosFichaCompleta($conn, $numero_ordem) {
    // 1. Consulta Principal (Header da Ficha)
    $sql_colab = "
        SELECT 
            e.numero_ordem, 
            e.data_entrega, 
            c.nome AS nome_colaborador, 
            c.matricula, 
            s.nome_setor AS setor_nome,
            e.id_entrega
        FROM entregas e
        JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
        -- ** CORREÇÃO APLICADA: c.setor_id **
        JOIN setores s ON c.setor_id = s.id_setor 
        WHERE e.numero_ordem = ?
    ";

    $stmt_colab = $conn->prepare($sql_colab);
    if ($stmt_colab === false) {
        // Erro de preparação SQL. Logar ou retornar erro.
        return false;
    }
    $stmt_colab->bind_param("s", $numero_ordem);
    $stmt_colab->execute();
    $colab_result = $stmt_colab->get_result();
    $ficha_header = $colab_result->fetch_assoc();
    $stmt_colab->close();

    if (!$ficha_header) {
        return false;
    }

    $id_entrega = $ficha_header['id_entrega'];

    // 2. Consulta Detalhada dos EPIs
    $sql_itens = "
        SELECT 
            i.quantidade, 
            p.descricao,
            p.ca_numero,
            p.unidade_medida
        FROM itens_entrega i
        JOIN produtos p ON i.id_produto = p.id_produto
        WHERE i.id_entrega = ?
        ORDER BY p.descricao
    ";

    $stmt_itens = $conn->prepare($sql_itens);
    $stmt_itens->bind_param("i", $id_entrega);
    $stmt_itens->execute();
    $itens_result = $stmt_itens->get_result();
    $itens = [];
    while ($row = $itens_result->fetch_assoc()) {
        $itens[] = $row;
    }
    $stmt_itens->close();

    return ['header' => $ficha_header, 'itens' => $itens];
}


// -----------------------------------------------------------------
// 🚨 3. LÓGICA DE AÇÕES (AJAX, PDF, CSV)
// -----------------------------------------------------------------

if ($conexao_status && isset($_GET['action'])) {
    
    $action = $_GET['action'];
    
    switch ($action) {
        
        // --- AÇÃO 3.1: AUTOCOMPLETAR (AJAX) ---
        case 'autocomplete':
            header('Content-Type: application/json');
            // Lógica de autocomplete (sem alterações, pois o JOIN não é usado aqui)
            if (isset($_GET['termo']) && !empty(trim($_GET['termo']))) {
                $termo = trim($_GET['termo']);
                $param_termo = "%" . $conn->real_escape_string($termo) . "%";

                $sql = "
                    (SELECT numero_ordem AS sugestao, 'Ordem' AS tipo FROM entregas WHERE numero_ordem LIKE ?)
                    UNION
                    (SELECT nome AS sugestao, 'Colaborador' AS tipo FROM colaboradores WHERE nome LIKE ?)
                    UNION
                    (SELECT matricula AS sugestao, 'Matrícula' AS tipo FROM colaboradores WHERE matricula LIKE ?)
                    LIMIT 10
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $param_termo, $param_termo, $param_termo);
                $stmt->execute();
                $result = $stmt->get_result();

                $sugestoes = [];
                while ($row = $result->fetch_assoc()) {
                    $sugestoes[] = $row['sugestao'] . " (" . $row['tipo'] . ")";
                }
                $stmt->close();
                echo json_encode($sugestoes);
            } else {
                echo json_encode([]);
            }
            $conn->close();
            exit; 

        // --- AÇÃO 3.2: GERAR PDF ---
        case 'pdf':
            if (!isset($_GET['ordem']) || empty($_GET['ordem'])) {
                die("Número de Ordem não fornecido para PDF.");
            }
            $numero_ordem = $_GET['ordem'];
            $dados_ficha = buscarDadosFichaCompleta($conn, $numero_ordem);

            if (!$dados_ficha) {
                 die("Ficha de entrega não encontrada para a ordem: " . htmlspecialchars($numero_ordem));
            }
            
            // 🚨 Lógica de Geração do PDF (Requer TCPDF ou similar)
            // Lembre-se de configurar e incluir sua biblioteca PDF aqui!
            
            // Simulação de saída do PDF
            header('Content-Type: text/plain; charset=utf-8');
            echo "--- SIMULAÇÃO DE PDF GERADO ---\n";
            echo "N° Ordem: {$dados_ficha['header']['numero_ordem']}\n";
            echo "Colaborador: {$dados_ficha['header']['nome_colaborador']}\n";
            echo "Setor: {$dados_ficha['header']['setor_nome']}\n";
            echo "Itens:\n";
            print_r($dados_ficha['itens']);
            
            $conn->close();
            exit; 

        // --- AÇÃO 3.3: EXPORTAR CSV ---
        case 'csv':
            if (!isset($_GET['ordem']) || empty($_GET['ordem'])) {
                die("Número de Ordem não fornecido para CSV.");
            }
            $numero_ordem = $_GET['ordem'];
            
            $sql_csv = "
                SELECT 
                    e.numero_ordem, 
                    DATE_FORMAT(e.data_entrega, '%d/%m/%Y %H:%i:%s') AS data_entrega_formatada,
                    c.nome AS nome_colaborador, 
                    c.matricula, 
                    s.nome_setor AS setor, 
                    p.descricao AS epi_descricao,
                    p.ca_numero AS epi_ca,
                    i.quantidade AS epi_quantidade, 
                    p.unidade_medida AS epi_unidade
                FROM entregas e
                JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
                -- ** CORREÇÃO APLICADA: c.setor_id **
                JOIN setores s ON c.setor_id = s.id_setor 
                JOIN itens_entrega i ON e.id_entrega = i.id_entrega
                JOIN produtos p ON i.id_produto = p.id_produto
                WHERE e.numero_ordem = ?
                ORDER BY p.descricao
            ";
            
            $stmt_csv = $conn->prepare($sql_csv);
            $stmt_csv->bind_param("s", $numero_ordem);
            $stmt_csv->execute();
            $result_csv = $stmt_csv->get_result();
            
            if ($result_csv->num_rows === 0) {
                 die("Nenhum item encontrado para a ordem: " . htmlspecialchars($numero_ordem));
            }

            // Geração e Download do CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Ficha_EPI_' . $numero_ordem . '.csv"');

            $output = fopen('php://output', 'w');
            
            // Para garantir que o Excel entenda acentuação
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
            
            $cabecalho = [
                'N_ORDEM', 'DATA_ENTREGA', 'COLABORADOR_NOME', 'COLABORADOR_MATRICULA', 
                'SETOR', 'EPI_DESCRICAO', 'EPI_CA', 'EPI_QUANTIDADE', 'EPI_UNIDADE'
            ];
            
            fputcsv($output, $cabecalho, ';');

            while ($row = $result_csv->fetch_assoc()) {
                // Remove a formatação de data desnecessária para o CSV, mas mantém a ordem
                $linha = array_values($row); 
                fputcsv($output, $linha, ';');
            }

            $stmt_csv->close();
            $conn->close();
            fclose($output);
            exit; 
    }
}


// -----------------------------------------------------------------
// 🚨 4. LÓGICA DE BUSCA PRINCIPAL (HTML View)
// -----------------------------------------------------------------
$resultados = [];
$termo_pesquisa = '';
$sucesso_busca = false;
$erro_busca = '';

if ($conexao_status && $_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['termo'])) {
    
    $termo_pesquisa = trim($_GET['termo']);
    
    if (!empty($termo_pesquisa)) {
        $sucesso_busca = true; 
        
        $param_termo = "%" . $conn->real_escape_string($termo_pesquisa) . "%";
        
        $sql = "
            SELECT 
                e.numero_ordem, 
                e.data_entrega, 
                c.nome AS nome_colaborador, 
                c.matricula, 
                s.nome_setor AS setor, 
                e.id_entrega
            FROM entregas e
            JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
            -- ** CORREÇÃO APLICADA: c.setor_id **
            JOIN setores s ON c.setor_id = s.id_setor 
            WHERE 
                e.numero_ordem LIKE ? OR 
                c.nome LIKE ? OR
                c.matricula LIKE ? 
            ORDER BY e.data_entrega DESC
            LIMIT 100
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $erro_busca = "Erro de sintaxe SQL: " . $conn->error;
        } else {
            $stmt->bind_param("sss", $param_termo, $param_termo, $param_termo); 
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
                }
            } else {
                $erro_busca = "Erro na execução da consulta: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
         $erro_busca = "Digite um termo para realizar a busca.";
    }
}

if ($conexao_status && !isset($_GET['action'])) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Entregas de EPI</title>
    <style>
        /* Estilos CSS simplificados */
        body { font-family: sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; background-color: white; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-radius: 10px; }
        .main-header { background-color: #007bff; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .search-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .search-form input[type="text"] { flex-grow: 1; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1.1em; }
        .search-form button { padding: 12px 20px; background-color: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; }
        .search-form button:hover { background-color: #1e7e34; }
        .results-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .results-table th, .results-table td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        .results-table th { background-color: #e9ecef; font-weight: 700; }
        .results-table tr:hover { background-color: #f8f9fa; }
        .btn-link { padding: 6px 12px; color: white; text-decoration: none; border-radius: 4px; font-size: 0.85em; margin-right: 5px; display: inline-block; }
        .btn-pdf { background-color: #dc3545; }
        .btn-pdf:hover { background-color: #c82333; }
        .btn-csv { background-color: #ffc107; color: #333; }
        .btn-csv:hover { background-color: #e0a800; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <h1>🛠️ Gerenciamento e Rastreabilidade de EPI</h1>
        </div>
    </header>

    <div class="container">
        <h2>Busca de Fichas de Entrega</h2>
        
        <?php if (!$conexao_status): ?>
             <div class="alert-error">
                <strong>Falha de Conexão:</strong> <?php echo $conexao_erro; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($erro_busca)): ?>
             <div class="alert-error">
                <strong>Erro na Busca:</strong> <?php echo $erro_busca; ?>
            </div>
        <?php endif; ?>
        
        <form method="GET" action="gerenciar_entregas.php" class="search-form">
            <input type="text" 
                   name="termo" 
                   id="termo-pesquisa"
                   placeholder="Buscar por N° de Ordem, Nome ou Matrícula" 
                   value="<?php echo htmlspecialchars($termo_pesquisa); ?>" 
                   required
                   list="sugestoes-busca">
            
            <datalist id="sugestoes-busca"></datalist>
            
            <button type="submit">🔍 Buscar Fichas</button>
        </form>

        <?php if ($sucesso_busca): ?>
            <h3>Resultados da Pesquisa (<?php echo count($resultados); ?> encontrados)</h3>
            <?php if (!empty($resultados)): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>N° Ordem</th>
                            <th>Data/Hora</th>
                            <th>Colaborador</th>
                            <th>Setor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $res): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($res['numero_ordem']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($res['data_entrega'])); ?></td>
                            <td><?php echo htmlspecialchars($res['nome_colaborador']); ?> (<?php echo htmlspecialchars($res['matricula']); ?>)</td>
                            <td><?php echo htmlspecialchars($res['setor']); ?></td>
                            <td>
                                <a href="gerenciar_entregas.php?action=pdf&ordem=<?php echo urlencode($res['numero_ordem']); ?>" class="btn-link btn-pdf" target="_blank">
                                    🖨️ PDF
                                </a>
                                <a href="gerenciar_entregas.php?action=csv&ordem=<?php echo urlencode($res['numero_ordem']); ?>" class="btn-link btn-csv">
                                    📄 CSV
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 15px; background-color: #f0f0f0; border-radius: 4px;">Nenhuma ficha de entrega encontrada com o termo "<?php echo htmlspecialchars($termo_pesquisa); ?>".</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('termo-pesquisa');
            const datalist = document.getElementById('sugestoes-busca');
            let timeout = null;

            input.addEventListener('input', function() {
                clearTimeout(timeout);
                const termo = this.value.trim();

                if (termo.length < 3) { 
                    datalist.innerHTML = '';
                    return;
                }

                timeout = setTimeout(function() {
                    // Chama o próprio arquivo com action=autocomplete para a lógica AJAX
                    const url = `gerenciar_entregas.php?action=autocomplete&termo=${encodeURIComponent(termo)}`;
                    
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            datalist.innerHTML = '';
                            
                            if (data.error) {
                                console.error("Erro na busca de sugestões:", data.error);
                                return;
                            }

                            data.forEach(sugestao => {
                                const option = document.createElement('option');
                                option.value = sugestao;
                                datalist.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Erro ao buscar sugestões:', error);
                        });
                }, 300);
            });
        });
    </script>

</body>
</html>