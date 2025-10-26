<?php
// --- 1. CONFIGURAÇÃO DO BANCO DE DADOS ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst');

// --- 2. CONEXÃO COM O BANCO DE DADOS ---
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Erro de Conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// -----------------------------------------------------------
// --- 3. INICIALIZAÇÃO E BUSCA DE DADOS ---
// -----------------------------------------------------------

$id_treinamento = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$treinamento = null;
$mensagem = '';
$mensagem_tipo = '';

if ($id_treinamento == 0) {
    die("<div style='color:red;padding:20px;'>ERRO: ID do Treinamento inválido ou não fornecido.</div>");
}

// Buscar dados de dropdowns
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

$tipos_treinamento_list = getDropdownData($conn, 'tipos_treinamento', 'id_tipo_treinamento', 'nome_tipo_treinamento');
$colaboradores_list = getDropdownData($conn, 'colaboradores', 'id_colaborador', 'nome');


// Query para buscar o treinamento a ser editado
$sql_select = "SELECT 
                    t.id_treinamento,
                    t.data_vencimento,
                    t.resp_treinamento,
                    t.id_colaborador,
                    t.id_tipo_treinamento,
                    c.nome AS nome_colaborador,
                    tt.nome_tipo_treinamento
               FROM 
                    treinamentos t
               JOIN colaboradores c ON t.id_colaborador = c.id_colaborador
               JOIN tipos_treinamento tt ON t.id_tipo_treinamento = tt.id_tipo_treinamento
               WHERE t.id_treinamento = ?";

if ($stmt = $conn->prepare($sql_select)) {
    $stmt->bind_param("i", $id_treinamento);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $treinamento = $result->fetch_assoc();
    } else {
        die("<div style='color:red;padding:20px;'>ERRO: Treinamento não encontrado.</div>");
    }
    $stmt->close();
} else {
    die("<div style='color:red;padding:20px;'>ERRO na preparação da consulta: " . $conn->error . "</div>");
}


// -----------------------------------------------------------
// --- 4. PROCESSAMENTO DO FORMULÁRIO (UPDATE) ---
// -----------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura e validação dos novos dados
    $novo_tipo_id = isset($_POST['id_tipo_treinamento']) && is_numeric($_POST['id_tipo_treinamento']) ? (int)$_POST['id_tipo_treinamento'] : $treinamento['id_tipo_treinamento'];
    $nova_data_vencimento = isset($_POST['data_vencimento']) ? trim($_POST['data_vencimento']) : $treinamento['data_vencimento'];
    $novo_resp = isset($_POST['resp_treinamento']) ? trim($_POST['resp_treinamento']) : $treinamento['resp_treinamento'];
    $novo_colab_id = isset($_POST['id_colaborador']) && is_numeric($_POST['id_colaborador']) ? (int)$_POST['id_colaborador'] : $treinamento['id_colaborador'];
    
    // Verificação mínima
    if (empty($nova_data_vencimento) || empty($novo_resp) || $novo_colab_id <= 0 || $novo_tipo_id <= 0) {
        $mensagem = "Todos os campos são obrigatórios.";
        $mensagem_tipo = "error";
    } else {
        // Query de UPDATE
        $sql_update = "UPDATE treinamentos SET 
                        id_tipo_treinamento = ?, 
                        data_vencimento = ?, 
                        resp_treinamento = ?,
                        id_colaborador = ?
                       WHERE id_treinamento = ?";
        
        if ($stmt = $conn->prepare($sql_update)) {
            $stmt->bind_param("isssi", 
                $novo_tipo_id, 
                $nova_data_vencimento, 
                $novo_resp,
                $novo_colab_id,
                $id_treinamento
            );
            
            if ($stmt->execute()) {
                $mensagem = "Treinamento atualizado com sucesso!";
                $mensagem_tipo = "success";
                
                // Recarregar os dados do registro após o sucesso para atualizar o formulário
                $treinamento['id_tipo_treinamento'] = $novo_tipo_id;
                $treinamento['data_vencimento'] = $nova_data_vencimento;
                $treinamento['resp_treinamento'] = $novo_resp;
                $treinamento['id_colaborador'] = $novo_colab_id;
                
                // Busca o nome do colaborador e tipo (se mudou) para o título
                // CORREÇÃO PARA COMPATIBILIDADE COM PHP < 7.4 (Função Anônima)
                $colab_nome_new = array_filter($colaboradores_list, function($c) use ($novo_colab_id) {
                    return $c['id_colaborador'] == $novo_colab_id;
                });
                // Fim da correção
                
                $treinamento['nome_colaborador'] = $colab_nome_new ? reset($colab_nome_new)['nome'] : 'N/A';

            } else {
                $mensagem = "Erro ao atualizar: " . $stmt->error;
                $mensagem_tipo = "error";
            }
            $stmt->close();
        } else {
            $mensagem = "Erro na preparação do UPDATE: " . $conn->error;
            $mensagem_tipo = "error";
        }
    }
}

// Fechar conexão no final do processamento
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Treinamento</title>
    
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { width: 90%; max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
        h1 { color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #34495e; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; box-sizing: border-box; 
        }
        .actions { display: flex; justify-content: space-between; margin-top: 30px; }
        .btn-submit { padding: 12px 25px; background-color: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #218838; }
        .btn-back { padding: 12px 25px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s; font-weight: 600; }
        .btn-back:hover { background-color: #5a6268; }
        
        /* Mensagens */
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="container">
        <h1>Editar Treinamento</h1>
        
        <?php if ($mensagem): ?>
            <div class="message <?php echo $mensagem_tipo; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <h2>Treinamento: <?php echo htmlspecialchars($treinamento['nome_colaborador'] ?? 'N/A') . ' - ' . htmlspecialchars($treinamento['nome_tipo_treinamento'] ?? 'N/A'); ?></h2>

        <form method="POST" action="editar_treinamento.php?id=<?php echo $id_treinamento; ?>">
            
            <div class="form-group">
                <label for="id_colaborador">Colaborador</label>
                <select id="id_colaborador" name="id_colaborador" required>
                    <?php foreach ($colaboradores_list as $c): ?>
                        <option value="<?php echo $c['id_colaborador']; ?>" 
                                <?php echo ($c['id_colaborador'] == ($treinamento['id_colaborador'] ?? $novo_colab_id) ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($c['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="id_tipo_treinamento">Tipo de Treinamento</label>
                <select id="id_tipo_treinamento" name="id_tipo_treinamento" required>
                    <?php foreach ($tipos_treinamento_list as $tt): ?>
                        <option value="<?php echo $tt['id_tipo_treinamento']; ?>" 
                                <?php echo ($tt['id_tipo_treinamento'] == ($treinamento['id_tipo_treinamento'] ?? $novo_tipo_id) ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($tt['nome_tipo_treinamento']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="data_vencimento">Data de Vencimento</label>
                <input type="date" id="data_vencimento" name="data_vencimento" 
                       value="<?php echo htmlspecialchars($treinamento['data_vencimento'] ?? $nova_data_vencimento ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="resp_treinamento">Responsável pelo Treinamento</label>
                <input type="text" id="resp_treinamento" name="resp_treinamento" 
                       value="<?php echo htmlspecialchars($treinamento['resp_treinamento'] ?? $novo_resp ?? ''); ?>" required>
            </div>

            <div class="actions">
                <a href="dashboard_treinamento.php" class="btn-back">Cancelar e Voltar</a>
                <button type="submit" class="btn-submit">Salvar Alterações</button>
            </div>
        </form>
    </div>

</body>
</html>