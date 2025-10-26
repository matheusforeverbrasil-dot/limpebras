<?php
session_start();
require_once 'conexao.php'; 
$mensagem_erro = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    if (empty($email) || empty($senha)) {
        $mensagem_erro = "Por favor, preencha todos os campos.";
    } elseif (!$conexao_status || !isset($conn) || !$conn->ping()) {
         $mensagem_erro = "Erro de conexão com o banco de dados.";
    } else {
        // Uso de Prepared Statement (Proteção contra SQL INJECTION)
        $sql = "SELECT id_usuario, nome, email, senha_hash, nivel_user, ativo_user 
                FROM usuarios 
                WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email); 
            $stmt->execute();
            $result = $stmt->get_result();  
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($senha, $user['senha_hash'])) {
                    if ($user['ativo_user'] == 0) {
                        $mensagem_erro = "Sua conta está inativa. Contate o administrador.";
                    } else {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['id_user'] = $user['id_usuario']; 
                        $_SESSION['login_user'] = $user['email']; 
                        $_SESSION['nome_user'] = $user['nome']; 
                        $_SESSION['nivel_user'] = $user['nivel_user']; 
                        header('Location: index.php'); 
                        exit;
                    }
                } else {
                    $mensagem_erro = "Email ou senha inválidos.";
                }
            } else {
                $mensagem_erro = "Email ou senha inválidos.";
            }
            $stmt->close();
        } else {
            $mensagem_erro = "Erro interno no sistema de autenticação.";
        }
        // Fechamento da conexão para garantir que não haja problemas
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Sistema SST</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="login.css">
    
</head>
<body>

<div class="login-container">
    <div class="logo-icon">
        <i class="fas fa-hard-hat"></i>
    </div>
    <h2>Acesso SST - Controle de EPI</h2>

    <?php if ($mensagem_erro): ?>
        <div class="alert-error"><?= $mensagem_erro ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <i class="fas fa-envelope"></i> 
            <input type="text" id="email" name="email" placeholder="Email de Login" required autofocus>
        </div>
        <div class="form-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="senha" name="senha" placeholder="Senha" required>
        </div>
        
        <div class="forgot-password">
            <a href="recuperar_senha.php">Esqueci minha senha</a> 
        </div>

        <button type="submit" name="login" class="btn-login">
            <i class="fas fa-sign-in-alt"></i> Entrar
        </button>
    </form>
</div>

</body>
</html>