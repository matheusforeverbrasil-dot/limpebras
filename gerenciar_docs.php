<?php
// =======================================================
// gerenciar_docs.php - Gerenciamento de Documentos (CRUD Simplificado)
// =======================================================

include 'conexao.php';
include 'header.php'; // Inclui ApexCharts e styles.css

/**
 * Função para formatar o tamanho do arquivo em B, KB, MB, GB, etc.
 * @param int $bytes O tamanho do arquivo em bytes.
 * @return string O tamanho formatado.
 */
function formatarTamanho($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    if ($bytes == 0) return '0 B';
    $i = floor(log($bytes, 1024));
    return round($bytes / (1024 ** $i), 2) . ' ' . $unidades[$i];
}

// =======================================================
// 1. LÓGICA DE PROCESSAMENTO DE AÇÕES (DELETE / UPDATE)
// =======================================================
$mensagem_sucesso = '';
$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Garante que o ID é um inteiro
    $id_doc = isset($_POST['id_documento']) ? (int)$_POST['id_documento'] : 0;
    $action = $_POST['action'];
    
    // Simulação do ID do usuário que está logado (usado para rastreabilidade)
    // Em um sistema real: $id_usuario_logado = $_SESSION['id_usuario'];
    $id_usuario_logado = 1; 

    if ($id_doc > 0) {
        $sql = '';
        $sucesso = false;
        $log_acao = ''; // Variável para rastreabilidade

        switch ($action) {
            case 'deletar':
                // Ação de DELETE (Exclusão Lógica: ativo = 0)
                $sql = "UPDATE documentos SET ativo = 0 WHERE id_documento = $id_doc";
                $sucesso = $conn->query($sql);
                $mensagem_sucesso = "Documento ID $id_doc foi desativado (Exclusão Lógica).";
                $log_acao = 'DELETAR_LOGICO'; 
                break;
                
            case 'proteger':
                // Ação de UPDATE (Mudar Status para 'Protegido' e com senha)
                $sql = "UPDATE documentos SET status_protecao = 'Protegido', protegido_por_senha = TRUE WHERE id_documento = $id_doc";
                $sucesso = $conn->query($sql);
                $mensagem_sucesso = "Documento ID $id_doc foi marcado como 'Protegido' e com senha.";
                $log_acao = 'MUDAR_STATUS_PROTEGIDO'; 
                break;
                
            case 'publico':
                // Ação de UPDATE (Mudar Status para 'Publico' e sem senha)
                $sql = "UPDATE documentos SET status_protecao = 'Publico', protegido_por_senha = FALSE WHERE id_documento = $id_doc";
                $sucesso = $conn->query($sql);
                $mensagem_sucesso = "Documento ID $id_doc foi marcado como 'Público'.";
                $log_acao = 'MUDAR_STATUS_PUBLICO'; 
                break;
                
            case 'restrito':
                // Ação de UPDATE (Mudar Status para 'Restrito')
                $sql = "UPDATE documentos SET status_protecao = 'Restrito' WHERE id_documento = $id_doc";
                $sucesso = $conn->query($sql);
                $mensagem_sucesso = "Documento ID $id_doc foi marcado como 'Restrito'.";
                $log_acao = 'MUDAR_STATUS_RESTRITO'; 
                break;
        }

        if ($sucesso) {
            // REGISTRA NO LOG APÓS A AÇÃO SER BEM-SUCEDIDA
            // Chama a função definida em conexao.php
            registrar_log($conn, $id_doc, $log_acao, $id_usuario_logado);
        } else {
            $mensagem_erro = "Erro ao executar a ação: " . $conn->error;
        }
    } else {
        $mensagem_erro = "ID de documento inválido.";
    }
}

// --------------------------------------------------------------------------------
// 2. CONSULTA DE LISTAGEM DE DOCUMENTOS
// --------------------------------------------------------------------------------

$sql_listagem = "
    SELECT 
        d.id_documento, 
        d.nome_arquivo, 
        d.extensao, 
        d.data_upload, 
        d.tamanho_bytes, 
        d.status_protecao,
        d.protegido_por_senha,
        d.ativo,
        u.nome AS nome_uploader
    FROM 
        documentos d
    LEFT JOIN
        usuarios u ON d.id_usuario = u.id_usuario
    ORDER BY 
        d.data_upload DESC
";

$result_listagem = $conn->query($sql_listagem);

if ($result_listagem === FALSE) {
    die("Erro ao listar documentos: " . $conn->error);
}
?>



<h2>Gerenciamento de Documentos e Permissões</h2>

<?php if ($mensagem_sucesso): ?>
    <div class="alerta-sucesso"><?= $mensagem_sucesso ?></div>
<?php endif; ?>
<?php if ($mensagem_erro): ?>
    <div class="alerta-erro"><?= $mensagem_erro ?></div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome do Arquivo</th>
            <th>Uploader</th>
            <th>Ext.</th>
            <th>Tamanho</th>
            <th>Status de Proteção</th>
            <th>Data Upload</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result_listagem->num_rows > 0): ?>
            <?php while($doc = $result_listagem->fetch_assoc()): ?>
                <tr class="<?= $doc['ativo'] ? '' : 'status-inativo' ?>">
                    <td><?= $doc['id_documento'] ?></td>
                    <td>
                        <?= htmlspecialchars($doc['nome_arquivo']) ?>
                        <?php if (!$doc['ativo']): ?> 
                            <span class="status-badge status-inativo">(INATIVO)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($doc['nome_uploader'] ?? 'N/A') ?></td>
                    <td>.<?= strtoupper($doc['extensao']) ?></td>
                    <td><?= formatarTamanho($doc['tamanho_bytes']) ?></td>
                    <td>
                        <span class="status-badge status-<?= $doc['status_protecao'] ?>">
                            <?= $doc['status_protecao'] ?>
                        </span>
                        <?php if ($doc['protegido_por_senha']): ?>
                            <span style="font-size: 0.8em; color: #e53935;" title="Protegido por Senha"> (🔒)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($doc['data_upload'])) ?></td>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja DELETAR (Inativar) o documento: <?= htmlspecialchars($doc['nome_arquivo']) ?>?');">
                                <input type="hidden" name="id_documento" value="<?= $doc['id_documento'] ?>">
                                <input type="hidden" name="action" value="deletar">
                                <button type="submit" class="btn btn-deletar" title="Inativar o arquivo">Deletar</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="id_documento" value="<?= $doc['id_documento'] ?>">
                                <input type="hidden" name="action" value="proteger">
                                <button type="submit" class="btn btn-proteger" title="Mudar para Protegido (Com senha)">Proteger</button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="id_documento" value="<?= $doc['id_documento'] ?>">
                                <input type="hidden" name="action" value="restrito">
                                <button type="submit" class="btn btn-restrito" title="Mudar para Restrito">Restrito</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="id_documento" value="<?= $doc['id_documento'] ?>">
                                <input type="hidden" name="action" value="publico">
                                <button type="submit" class="btn btn-publico" title="Mudar para Público">Público</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">Nenhum documento encontrado no banco de dados.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
// Fecha o footer e a conexão
include 'footer.php';
$conn->close();
?>