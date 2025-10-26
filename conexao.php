<?php
// Configurações de Conexão (Ajuste conforme o seu ambiente)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistemasst'); 
define('DB_PORT', 3306); 

// 1. Inicializa as variáveis no escopo GLOBAL
$conexao_status = true;
$conexao_erro = '';
$conn = null; // Inicializa a conexão como nula

// 2. Tenta conectar
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// 3. Verifica o status da conexão
if ($conn->connect_error) {
    $conexao_status = false;
    $conexao_erro = "Erro de Conexão: " . htmlspecialchars($conn->connect_error);
    $conn = null; // Garante que $conn seja null se a conexão falhar
} else {
    // Define o charset se a conexão for bem-sucedida
    $conn->set_charset("utf8mb4");
}
// =======================================================
// NOVA FUNÇÃO: Rastreabilidade (Log de Ações)
// =======================================================
/**
 * Registra uma ação ou acesso no log de documentos.
 * @param mysqli $conn Objeto de conexão MySQLi.
 * @param int $id_documento ID do documento afetado.
 * @param string $acao Descrição da ação (Ex: 'DELETAR_LOGICO', 'MUDAR_STATUS_PROTEGIDO').
 * @param int|null $id_usuario ID do usuário que realizou a ação (Simulado ou Real).
 */
function registrar_log($conn, $id_documento, $acao, $id_usuario = 1) {
    // Usamos 1 como usuário padrão para simulação, pois não temos login.
    // Em produção, $id_usuario viria da sessão: $_SESSION['user_id']
    $id_usuario_real = (int)$id_usuario; 
    
    // Pega o IP do cliente (para rastreabilidade básica)
    $ip_acesso = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO log_acessos (id_documento, id_usuario, data_acesso, ip_acesso, acao_log) VALUES (?, ?, NOW(), ?, ?)");
    
    // Adicionamos a coluna 'acao_log' à consulta. É ideal que ela exista na tabela log_acessos.
    // Se você não adicionou 'acao_log', substitua a linha acima por:
    // $stmt = $conn->prepare("INSERT INTO log_acessos (id_documento, id_usuario, data_acesso, ip_acesso) VALUES (?, ?, NOW(), ?)");
    
    if ($stmt) {
        // Se a tabela log_acessos tiver 'acao_log' (VARCHAR(255) recomendado):
        if (isset($acao)) {
            $stmt->bind_param("iiss", $id_documento, $id_usuario_real, $ip_acesso, $acao);
        } else {
            // Se a tabela log_acessos NÃO tiver 'acao_log':
            $stmt->bind_param("iis", $id_documento, $id_usuario_real, $ip_acesso);
        }
        
        $stmt->execute();
        $stmt->close();
    } else {
        // Opcional: registrar erro em arquivo de log no servidor
        // error_log("Erro ao preparar log: " . $conn->error);
    }
}