<?php
// =======================================================
// registro_requisicoes.php - Upload e Rastreamento de Requisições PDF
// =======================================================

$pageTitle = "Registro de Requisições de EPI - SST"; 
require_once 'header.php'; 

// Diretório onde os PDFs serão salvos
$upload_dir = 'requisicoes_epi/';

// Cria o diretório se ele não existir
if (!is_dir($upload_dir)) {
    // Tenta criar o diretório com permissão total (0777)
    if (!mkdir($upload_dir, 0777, true)) {
         $mensagem_status = '<div class="alert-error">ERRO CRÍTICO: Falha ao criar o diretório de upload. Verifique as permissões de pasta.</div>';
    }
}

$mensagem_status = '';
// ID do colaborador logado (ID REALISTA - DEVE VIR DA SESSÃO em um sistema real)
// Mantido fixo em 1 para permitir testes SEM um sistema de login implementado.
// EM PRODUÇÃO: $id_colaborador_logado = $_SESSION['id_colaborador'];
$id_colaborador_logado = 1; 

// =======================================================
// LÓGICA DE UPLOAD
// =======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['requisicao_pdf'])) {
    
    // Verifica se a conexão com o banco está ativa antes de prosseguir com o upload
    if (!$conexao_status || !isset($conn) || !$conn->ping()) {
        $mensagem_status = '<div class="alert-error"><strong>Falha Crítica no Sistema:</strong> Não foi possível processar. O banco de dados está offline.</div>';
    } else {
        $file = $_FILES['requisicao_pdf'];
        $nome_original = basename($file['name']);
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $mensagem_status = '<div class="alert-error">Erro no upload: Código ' . $file['error'] . '</div>';
        } elseif ($extensao != 'pdf') {
            $mensagem_status = '<div class="alert-error">Apenas arquivos PDF são permitidos para requisições.</div>';
        } else {
            // Nome de arquivo seguro e único para evitar colisões
            $nome_seguro = uniqid('req_') . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $nome_original);
            $caminho_completo = $upload_dir . $nome_seguro;

            if (move_uploaded_file($file['tmp_name'], $caminho_completo)) {
                // REGISTRA NO BANCO DE DADOS
                // Usando o campo 'id_colaborador_upload'
                $sql_insert = "INSERT INTO requisicoes_epi (nome_arquivo, caminho_arquivo, id_colaborador_upload) VALUES (?, ?, ?)";
                
                if ($stmt = $conn->prepare($sql_insert)) {
                    $stmt->bind_param("ssi", $nome_original, $caminho_completo, $id_colaborador_logado);
                    $stmt->execute();
                    $stmt->close();
                    
                    $mensagem_status = '<div class="alert-success">Requisição "' . htmlspecialchars($nome_original) . '" enviada e registrada com sucesso!</div>';
                } else {
                    $mensagem_status = '<div class="alert-error">Erro ao registrar no banco: ' . htmlspecialchars($conn->error) . '</div>';
                }
            } else {
                $mensagem_status = '<div class="alert-error">Falha ao mover o arquivo para o servidor. Verifique permissões (0777) da pasta "' . $upload_dir . '".</div>';
            }
        }
    }
}

// =======================================================
// LÓGICA DE PESQUISA E LISTAGEM
// =======================================================
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$lista_requisicoes = [];
$sql_search = "
    SELECT 
        req.id_requisicao, 
        req.nome_arquivo, 
        req.caminho_arquivo, 
        req.data_upload,
        c.nome AS nome_uploader
    FROM requisicoes_epi req
    LEFT JOIN colaboradores c ON req.id_colaborador_upload = c.id_colaborador
";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql_search .= "WHERE req.nome_arquivo LIKE ? ";
    $params[] = "%$search_query%";
    $types = 's';
}

$sql_search .= "ORDER BY req.data_upload DESC LIMIT 20";

if (isset($conn) && $conn->ping()) {
    if ($stmt = $conn->prepare($sql_search)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $lista_requisicoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}


?>

<h2>Registro e Pesquisa de Requisições (Pedidos de Compra)</h2>

<div class="card-area">
    <h3>Upload de Nova Requisição PDF</h3>
    <?= $mensagem_status ?>
    <form method="POST" enctype="multipart/form-data" class="upload-form">
        <label for="requisicao_pdf">Selecione o arquivo PDF da requisição:</label>
        <input type="file" name="requisicao_pdf" id="requisicao_pdf" accept=".pdf" required class="form-control-file">
        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-upload"></i> Enviar e Registrar</button>
    </form>
</div>

<div class="card-area mt-4">
    <h3>Pesquisar Requisições Registradas</h3>
    <form method="GET" class="search-form-inline">
        <input type="text" name="q" placeholder="Buscar por nome do arquivo..." value="<?= htmlspecialchars($search_query) ?>" class="form-control-inline">
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Pesquisar</button>
    </form>

    <h4 class="mt-4"><?= empty($search_query) ? 'Últimos 20 Registros' : 'Resultados da Pesquisa' ?>:</h4>
    <table class="simple-list-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Data</th>
                <th>Nome da Requisição</th>
                <th>Enviado por</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($lista_requisicoes)): ?>
                <?php foreach ($lista_requisicoes as $req): ?>
                <tr>
                    <td><?= htmlspecialchars($req['id_requisicao']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($req['data_upload'])) ?></td>
                    <td><?= htmlspecialchars($req['nome_arquivo']) ?></td>
                    <td><?= htmlspecialchars($req['nome_uploader'] ?? 'N/A') ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($req['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-view" title="Visualizar PDF">
                            <i class="fas fa-file-pdf"></i> Abrir
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">Nenhuma requisição encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>