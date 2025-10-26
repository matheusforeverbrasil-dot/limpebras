<?php
// =======================================================
// gerar_hash.php (CRUD COMPLETO - USO ADMINISTRATIVO TEMPORÁRIO)
// AVISO: SEM SESSÃO, SEM LOGIN, SEM PROTEÇÃO DE NÍVEL DE ACESSO!
// EXCLUA ESTE ARQUIVO APÓS CONCLUIR SUA TAREFA ADMINISTRATIVA.
// =======================================================

// --- 1. CONFIGURAÇÃO DO BANCO DE DADOS (AJUSTE AQUI!) ---
$db_host = 'localhost'; // Host do seu banco de dados
$db_user = 'root';      // Usuário do banco
$db_pass = '';          // Senha do banco
$db_name = 'sistemasst'; // Nome do seu banco de dados
// --------------------------------------------------------

// --- 2. CONEXÃO COM O BANCO DE DADOS ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

$pageTitle = "Gerenciamento de Usuários (DESPROTEGIDO)";
$mensagem_status = '';
$usuario_editar = null; 
$id_edicao = isset($_GET['id']) ? (int)$_GET['id'] : 0;


// =======================================================
// 3. LÓGICA DE PROCESSAMENTO (CRUD)
// =======================================================

// --- A. Lógica de Desativar/Ativar (Toggle) ---
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['id_user'])) {
    $target_id = (int)$_GET['id_user'];
    
    $sql_toggle = "UPDATE usuarios SET ativo_user = 1 - ativo_user WHERE id_usuario = ?";
    $stmt_toggle = $conn->prepare($sql_toggle);
    $stmt_toggle->bind_param("i", $target_id);
    if ($stmt_toggle->execute()) {
        $mensagem_status = '<div class="alert-success">Status do usuário alterado com sucesso!</div>';
    } else {
         $mensagem_status = '<div class="alert-error">Erro ao alterar status: ' . htmlspecialchars($conn->error) . '</div>';
    }
    $stmt_toggle->close();
}

// --- B. Lógica de Busca para Edição ---
if ($id_edicao > 0) {
    $sql_edit = "SELECT id_usuario, nome, email, nivel_user, ativo_user FROM usuarios WHERE id_usuario = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param("i", $id_edicao);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    
    if ($result_edit->num_rows == 1) {
        $usuario_editar = $result_edit->fetch_assoc();
    }
    $stmt_edit->close();
}

// --- C. Processamento de Cadastro/Edição ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_user'])) {
    
    $id_usuario_form = (int)($_POST['id_usuario'] ?? 0);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $nivel = (int)$_POST['nivel_user'];
    $ativo = (int)$_POST['ativo_user'];
    
    if ($id_usuario_form > 0) {
        // Modo Edição
        if (!empty($senha)) {
            $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nome=?, email=?, nivel_user=?, ativo_user=?, senha_hash=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiisi", $nome, $email, $nivel, $ativo, $hash_senha, $id_usuario_form);
        } else {
            $sql = "UPDATE usuarios SET nome=?, email=?, nivel_user=?, ativo_user=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $nome, $email, $nivel, $ativo, $id_usuario_form);
        }
        
        if ($stmt->execute()) {
            $mensagem_status = '<div class="alert-success">Usuário atualizado com sucesso!</div>';
        } else {
            $mensagem_status = '<div class="alert-error">Erro ao atualizar usuário: ' . htmlspecialchars($conn->error) . '</div>';
        }
        $stmt->close();
        
    } else {
        // Modo Criação (Cadastro)
        if (empty($senha)) {
            $mensagem_status = '<div class="alert-error">A senha é obrigatória para um novo usuário.</div>';
        } else {
            // Checa se o email já existe
            $check_sql = "SELECT id_usuario FROM usuarios WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                 $mensagem_status = '<div class="alert-error">ERRO: O email "' . htmlspecialchars($email) . '" já existe.</div>';
            } else {
                // Insere novo usuário
                $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nome, email, senha_hash, nivel_user, ativo_user) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                // CORREÇÃO APLICADA: "sssii" (3 strings, 2 integers)
                $stmt->bind_param("sssii", $nome, $email, $hash_senha, $nivel, $ativo);
                
                if ($stmt->execute()) {
                    $mensagem_status = '<div class="alert-success">Usuário "' . htmlspecialchars($nome) . '" cadastrado com sucesso!</div>';
                } else {
                    $mensagem_status = '<div class="alert-error">Erro ao cadastrar: ' . htmlspecialchars($conn->error) . '</div>';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}


// --- D. LÓGICA DE LISTAGEM ---
$lista_usuarios = [];
$sql_list = "SELECT id_usuario, nome, email, nivel_user, ativo_user FROM usuarios ORDER BY nome ASC";
$result_list = $conn->query($sql_list);
if ($result_list) {
    $lista_usuarios = $result_list->fetch_all(MYSQLI_ASSOC);
}


// =======================================================
// 4. HTML E CSS INTEGRADO
// =======================================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* --- RESET BÁSICO E LAYOUT GERAL --- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #343a40;
        }
        .container { 
            width: 95%; 
            max-width: 1200px; 
            margin: 20px auto; 
            background-color: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05); 
        }

        /* --- HEADER ADMINISTRATIVO --- */
        header {
            border-bottom: 3px solid #dc3545; /* Linha de aviso (vermelha) */
            margin-bottom: 30px;
            padding-bottom: 15px;
            text-align: center;
        }
        header h1 {
            font-size: 1.8em;
            color: #dc3545;
            margin: 0 0 5px 0;
        }
        header p {
            font-size: 1em;
            color: #721c24;
            font-weight: bold;
            background-color: #f8d7da;
            padding: 8px;
            border-radius: 4px;
        }

        /* --- TÍTULOS --- */
        h2 {
            color: #343a40;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 25px;
            font-size: 1.8em;
        }
        h3 {
            color: #495057;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .mt-3 { margin-top: 1rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }

        /* --- CARDS E FORMULÁRIOS --- */
        .card-area {
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background-color: #ffffff;
            margin-bottom: 30px;
        }
        .user-form .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .user-form .form-group-half { flex: 1; }
        .user-form .form-group-full { width: 100%; }
        .user-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* --- BOTÕES --- */
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5a6268; }
        .btn-edit { background-color: #ffc107; color: #343a40; }
        .btn-edit:hover { background-color: #e0a800; }
        .btn-delete { background-color: #dc3545; color: white; }
        .btn-delete:hover { background-color: #c82333; }

        /* --- TABELAS --- */
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .data-table th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
        }
        .data-table tr:hover { background-color: #f8f9fa; }
        .action-column { min-width: 180px; text-align: center; }
        .action-column a, .action-column button { margin-right: 5px; }

        /* --- ALERTAS --- */
        .alert-success {
            padding: 15px; margin-bottom: 20px; border-radius: 4px;
            color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb;
            font-weight: 600;
        }
        .alert-error {
            padding: 15px; margin-bottom: 20px; border-radius: 4px;
            color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb;
            font-weight: 600;
        }

        /* --- BADGES NÍVEL DE ACESSO --- */
        .status-badge {
            padding: 4px 8px; border-radius: 3px; font-size: 0.8em; font-weight: 700;
            line-height: 1; text-align: center; white-space: nowrap; display: inline-block;
        }
        .status-badge.status-ok { background-color: #28a745; color: white; }
        .status-badge.status-critico { background-color: #6c757d; color: white; }
        .status-badge.status-admin { background-color: #dc3545; color: white; }
        .status-badge.status-gerente { background-color: #ffc107; color: #343a40; }
        .status-badge.status-colaborador { background-color: #17a2b8; color: white; }
        .status-badge.status-funcionario { background-color: #00bcd4; color: white; }
        .status-badge.status-visitante { background-color: #607d8b; color: white; }
    </style>
</head>
<body>

<div class="container">
    
    <header>
        <h1>Sistema SST - Gerenciamento de Usuários</h1>
        <p><i class="fas fa-exclamation-triangle"></i> ESTA PÁGINA ESTÁ **DESPROTEGIDA**. EXCLUA APÓS O USO.</p>
    </header>

    <h2>Gerenciamento de Contas de Acesso</h2>

    <?= $mensagem_status ?>

    <div class="card-area">
        <h3><?= $usuario_editar ? 'Editar Usuário: ' . htmlspecialchars($usuario_editar['nome'] ?? $usuario_editar['email']) : 'Cadastrar Novo Usuário' ?></h3>
        <form method="POST" action="gerar_hash.php" class="user-form">
            
            <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($usuario_editar['id_usuario'] ?? 0) ?>">
            
            <div class="form-row">
                <div class="form-group-full">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" 
                           value="<?= htmlspecialchars($usuario_editar['nome'] ?? '') ?>" 
                           required class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group-half">
                    <label for="email">Email de Login:</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($usuario_editar['email'] ?? '') ?>" 
                           required class="form-control">
                </div>
                
                <div class="form-group-half">
                    <label for="senha">Senha (<?= $usuario_editar ? 'Deixe em branco para manter a atual' : 'Obrigatório' ?>):</label>
                    <input type="password" id="senha" name="senha" class="form-control" 
                           <?= $usuario_editar ? '' : 'required' ?>>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group-half">
                    <label for="nivel_user">Nível de Acesso:</label>
                    <select id="nivel_user" name="nivel_user" required class="form-control">
                        <option value="1" <?= ($usuario_editar && $usuario_editar['nivel_user'] == 1) ? 'selected' : '' ?>>1 - Administrador (Total)</option>
                        <option value="2" <?= ($usuario_editar && $usuario_editar['nivel_user'] == 2) ? 'selected' : '' ?>>2 - SST / Gerente</option>
                        <option value="3" <?= ($usuario_editar && $usuario_editar['nivel_user'] == 3) ? 'selected' : '' ?>>3 - Colaborador</option>
                        <option value="4" <?= ($usuario_editar && $usuario_editar['nivel_user'] == 4) ? 'selected' : '' ?>>4 - Funcionário</option>
                        <option value="5" <?= ($usuario_editar && $usuario_editar['nivel_user'] == 5) ? 'selected' : '' ?>>5 - Visitante (Apenas Consultas)</option>
                    </select>
                </div>

                <div class="form-group-half">
                    <label for="ativo_user">Status da Conta:</label>
                    <select id="ativo_user" name="ativo_user" required class="form-control">
                        <option value="1" <?= (!isset($usuario_editar) || $usuario_editar['ativo_user'] == 1) ? 'selected' : '' ?>>1 - Ativa</option>
                        <option value="0" <?= ($usuario_editar && $usuario_editar['ativo_user'] == 0) ? 'selected' : '' ?>>0 - Inativa (Bloqueada)</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="submit_user" class="btn btn-primary mt-3">
                <i class="fas fa-save"></i> <?= $usuario_editar ? 'Salvar Alterações' : 'Cadastrar Usuário' ?>
            </button>
            <?php if ($usuario_editar): ?>
                <a href="gerar_hash.php" class="btn btn-secondary mt-3"><i class="fas fa-plus"></i> Novo Cadastro</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-area mt-4">
        <h3>Usuários Registrados (<?= count($lista_usuarios) ?>)</h3>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lista_usuarios)): ?>
                        <?php foreach ($lista_usuarios as $user): 
                            // Variáveis de Status e Nível
                            $status_text = $user['ativo_user'] == 1 
                                ? '<span class="status-badge status-ok">Ativo</span>' 
                                : '<span class="status-badge status-critico">Inativo</span>';
                            
                            // Usando a estrutura switch (Compatível com PHP 7.x)
                            switch ($user['nivel_user']) {
                                case 1:
                                    $nivel_text = '<span class="status-badge status-admin">Admin</span>';
                                    break;
                                case 2:
                                    $nivel_text = '<span class="status-badge status-gerente">SST/Gerente</span>';
                                    break;
                                case 3:
                                    $nivel_text = '<span class="status-badge status-colaborador">Colaborador</span>';
                                    break;
                                case 4:
                                    $nivel_text = '<span class="status-badge status-funcionario">Funcionário</span>';
                                    break;
                                case 5:
                                    $nivel_text = '<span class="status-badge status-visitante">Visitante</span>';
                                    break;
                                default:
                                    $nivel_text = 'Desconhecido';
                                    break;
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id_usuario']) ?></td>
                            <td><?= htmlspecialchars($user['nome']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= $nivel_text ?></td>
                            <td><?= $status_text ?></td>
                            <td class="action-column">
                                <a href="?id=<?= $user['id_usuario'] ?>" class="btn btn-sm btn-edit" title="Editar">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                
                                <a href="?action=toggle&id_user=<?= $user['id_usuario'] ?>" 
                                   onclick="return confirm('Deseja realmente <?= $user['ativo_user'] == 1 ? 'DESATIVAR' : 'ATIVAR' ?> o usuário <?= htmlspecialchars($user['nome']) ?>?')" 
                                   class="btn btn-sm <?= $user['ativo_user'] == 1 ? 'btn-delete' : 'btn-primary' ?>" 
                                   title="<?= $user['ativo_user'] == 1 ? 'Desativar Conta' : 'Ativar Conta' ?>">
                                    <i class="fas <?= $user['ativo_user'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i> 
                                    <?= $user['ativo_user'] == 1 ? 'Desativar' : 'Ativar' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">Nenhum usuário cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.8em; color: #6c757d;">
        Sistema SST - Ferramenta Administrativa Temporária | Lembre-se de EXCLUIR este arquivo!
    </footer>

</div>

<?php 
// Fecha a conexão no final
$conn->close();
?>
</body>
</html>