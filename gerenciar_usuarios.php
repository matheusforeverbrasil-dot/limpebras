<?php
// =======================================================
// gerenciar_usuarios.php - Gerenciamento de Contas de Usuário (Apenas Admin)
// =======================================================

$pageTitle = "Gerenciamento de Usuários e Acessos";
require_once 'header.php'; // Inclui a sessão, conexão e a proteção de login

// --- 1. PROTEÇÃO DE NÍVEL DE ACESSO (CRUCIAL) ---
// Nível 1 = Administrador. Apenas ele pode gerenciar usuários.
if (!isset($_SESSION['nivel_user']) || $_SESSION['nivel_user'] != 1) {
    echo '<div class="alert-error">ACESSO NEGADO: Você não tem permissão para acessar esta área.</div>';
    require_once 'footer.php';
    exit;
}
// --------------------------------------------------

$mensagem_status = '';
$usuario_editar = null; // Para preencher o formulário em modo de edição
$id_edicao = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// =======================================================
// 2. LÓGICA DE PROCESSAMENTO (CRUD - CRIAÇÃO / EDIÇÃO / DESATIVAÇÃO)
// =======================================================

// --- A. Lógica de Desativar/Ativar (Toggle) ---
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['id_user'])) {
    $target_id = (int)$_GET['id_user'];
    
    // Evita que o administrador altere o próprio status
    if ($target_id === $_SESSION['id_user']) {
        $mensagem_status = '<div class="alert-error">Ação não permitida: Você não pode alterar o status da sua própria conta.</div>';
    } else {
        // Inverte o status de ativo_user (de 1 para 0, ou 0 para 1)
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
    
    // 1. Coleta e Sanitização
    $id_usuario_form = (int)($_POST['id_usuario'] ?? 0);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $nivel = (int)$_POST['nivel_user'];
    $ativo = (int)$_POST['ativo_user'];
    
    if ($id_usuario_form > 0) {
        // --- Modo Edição ---
        if (!empty($senha)) {
            // Se a senha for fornecida, atualiza o nome, email, nível, status E a senha_hash
            $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nome=?, email=?, nivel_user=?, ativo_user=?, senha_hash=? WHERE id_usuario=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiisi", $nome, $email, $nivel, $ativo, $hash_senha, $id_usuario_form);
        } else {
            // Se a senha estiver vazia, atualiza apenas nome, email, nível e status
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
        // --- Modo Criação (Cadastro) ---
        if (empty($senha)) {
            $mensagem_status = '<div class="alert-error">A senha é obrigatória para um novo usuário.</div>';
        } else {
            // 2. Checa se o email já existe
            $check_sql = "SELECT id_usuario FROM usuarios WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                 $mensagem_status = '<div class="alert-error">ERRO: O email "' . htmlspecialchars($email) . '" já existe.</div>';
            } else {
                // 3. Insere novo usuário
                $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nome, email, senha_hash, nivel_user, ativo_user) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $nome, $email, $hash_senha, $nivel, $ativo);
                
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


// =======================================================
// 3. LÓGICA DE LISTAGEM
// =======================================================

$lista_usuarios = [];
$sql_list = "SELECT id_usuario, nome, email, nivel_user, ativo_user FROM usuarios ORDER BY nome ASC";
$result_list = $conn->query($sql_list);
if ($result_list) {
    $lista_usuarios = $result_list->fetch_all(MYSQLI_ASSOC);
}


// =======================================================
// 4. HTML
// =======================================================
?>

<h2>Gerenciamento de Contas de Acesso</h2>

<?= $mensagem_status ?>

<div class="card-area">
    <h3><?= $usuario_editar ? 'Editar Usuário: ' . htmlspecialchars($usuario_editar['nome'] ?? $usuario_editar['email']) : 'Cadastrar Novo Usuário' ?></h3>
    <form method="POST" action="gerenciar_usuarios.php" class="user-form">
        
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
            <a href="gerenciar_usuarios.php" class="btn btn-secondary mt-3"><i class="fas fa-plus"></i> Novo Cadastro</a>
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
                        $is_self = $user['id_usuario'] === $_SESSION['id_user']; 
                        $status_text = $user['ativo_user'] == 1 
                            ? '<span class="status-badge status-ok">Ativo</span>' 
                            : '<span class="status-badge status-critico">Inativo</span>';
                        
                        // CORREÇÃO: Usando a estrutura switch (Compatível com PHP 7.x)
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
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if (!$is_self): ?>
                                <a href="?action=toggle&id_user=<?= $user['id_usuario'] ?>" 
                                   onclick="return confirm('Deseja realmente <?= $user['ativo_user'] == 1 ? 'DESATIVAR' : 'ATIVAR' ?> o usuário <?= htmlspecialchars($user['nome']) ?>?')" 
                                   class="btn btn-sm <?= $user['ativo_user'] == 1 ? 'btn-delete' : 'btn-primary' ?>" 
                                   title="<?= $user['ativo_user'] == 1 ? 'Desativar Conta' : 'Ativar Conta' ?>">
                                    <i class="fas <?= $user['ativo_user'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i> 
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled title="Não é possível alterar sua própria conta">
                                    <i class="fas fa-lock"></i>
                                </button>
                            <?php endif; ?>
                            
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

<?php 
// Fecha a conexão e inclui o rodapé
$conn->close();
require_once 'footer.php'; 
?>