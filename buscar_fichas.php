<?php
// =================================================================
// SISTEMA_RASTREABILIDADE.PHP - BUSCA, RESULTADOS E AUTOCOMPLETAR (SINGLE FILE)
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
}
if ($conexao_status) {
    $conn->set_charset("utf8mb4");
}

$resultados = [];
$termo_pesquisa = '';
$sucesso_busca = false;
$erro_busca = '';

// -----------------------------------------------------------------
// 🚨 2. LÓGICA DE PROCESSAMENTO (AJAX / BUSCA DE SUGESTÕES)
// -----------------------------------------------------------------
if ($conexao_status && isset($_GET['action']) && $_GET['action'] == 'autocomplete') {
    header('Content-Type: application/json');
    
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
        if ($stmt === false) {
            echo json_encode(['error' => 'Erro SQL: ' . $conn->error]);
            $conn->close();
            exit;
        }

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
    exit; // Termina o script após a resposta AJAX
}

// -----------------------------------------------------------------
// 🚨 3. LÓGICA DE PROCESSAMENTO (BUSCA PRINCIPAL)
// -----------------------------------------------------------------
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
                c.setor,
                e.id_entrega
            FROM entregas e
            JOIN colaboradores c ON e.id_colaborador = c.id_colaborador
            WHERE 
                e.numero_ordem LIKE ? OR 
                c.nome LIKE ? OR
                c.matricula LIKE ? 
            ORDER BY e.data_entrega DESC
            LIMIT 100
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            // Captura o erro da preparação do SQL (Corrigindo o Fatal Error)
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

if ($conexao_status) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Fichas de Entrega - SST</title>
    <style>
        /* Estilos CSS Base */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background-color: white; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); border-radius: 8px; }
        .main-header { background-color: #007bff; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-content { max-width: 1300px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .main-header h1 { margin: 0; font-size: 1.5em; }
        .main-header nav a { color: white; text-decoration: none; margin-left: 20px; padding: 5px 10px; border-radius: 4px; transition: background-color 0.2s; }
        .main-header nav a:hover { background-color: #0056b3; }
        
        h2 { border-bottom: 2px solid #ddd; padding-bottom: 5px; margin-top: 20px; }
        
        /* Estilos de Pesquisa */
        .search-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .search-form input[type="text"] { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .search-form button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .search-form button:hover { background-color: #0056b3; }
        
        /* Estilos da Tabela de Resultados */
        .results-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .results-table th, .results-table td { padding: 10px 15px; border-bottom: 1px solid #eee; text-align: left; font-size: 0.9em; }
        .results-table th { background-color: #f2f2f2; font-weight: 600; }
        .btn-link { padding: 5px 10px; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8em; margin-right: 5px; display: inline-block; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <h1>🔍 Rastreabilidade de Entregas de EPI</h1>
            <nav>
                <a href="epi_dashboard.php">Dashboard</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Pesquisar Fichas de Entrega</h2>
        
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
        
        <form method="GET" action="sistema_rastreabilidade.php" class="search-form">
            <input type="text" 
                   name="termo" 
                   id="termo-pesquisa"
                   placeholder="Buscar por N° de Ordem, Nome ou Matrícula" 
                   value="<?php echo htmlspecialchars($termo_pesquisa); ?>" 
                   required
                   list="sugestoes-busca">
            
            <datalist id="sugestoes-busca"></datalist>
            
            <button type="submit">Pesquisar</button>
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
                                <a href="gerar_ficha_pdf.php?ordem=<?php echo urlencode($res['numero_ordem']); ?>" class="btn-link" target="_blank" style="background-color: #dc3545;">
                                    🖨️ PDF
                                </a>
                                <a href="exportar_csv.php?ordem=<?php echo urlencode($res['numero_ordem']); ?>" class="btn-link" style="background-color: #ffc107; color: #333;">
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

                // Busca se tiver 3 ou mais caracteres
                if (termo.length < 3) { 
                    datalist.innerHTML = '';
                    return;
                }

                // Atraso de 300ms (debounce)
                timeout = setTimeout(function() {
                    // Chama o script PHP (o próprio arquivo) com o parâmetro de ação AJAX
                    const url = `sistema_rastreabilidade.php?action=autocomplete&termo=${encodeURIComponent(termo)}`;
                    
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            datalist.innerHTML = ''; // Limpa sugestões antigas
                            
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