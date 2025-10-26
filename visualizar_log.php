<?php
// =======================================================
// visualizar_log.php - Visualização do Histórico de Ações
// =======================================================

include 'conexao.php';
include 'header.php';

// Consulta para buscar os logs, juntando com o nome do arquivo e do usuário
$sql_logs = "
    SELECT 
        l.id_log,
        l.data_acesso,
        l.ip_acesso,
        l.acao_log,
        d.nome_arquivo,
        u.nome AS nome_usuario
    FROM 
        log_acessos l
    JOIN 
        documentos d ON l.id_documento = d.id_documento
    JOIN 
        usuarios u ON l.id_usuario = u.id_usuario
    ORDER BY 
        l.data_acesso DESC
    LIMIT 50"; // Limita para evitar sobrecarga

$result_logs = $conn->query($sql_logs);

if ($result_logs === FALSE) {
    die("Erro ao buscar logs: " . $conn->error);
}
?>

<style>
    /* Estilos para a tabela de logs */
    .log-table th, .log-table td { font-size: 0.9em; }
    .log-table th { background-color: #e3f2fd; }
</style>

<h2>Rastreabilidade e Histórico de Ações (Log)</h2>
<p>Exibindo as últimas 50 ações de gerenciamento e acesso aos documentos.</p>

<table class="log-table">
    <thead>
        <tr>
            <th>ID Log</th>
            <th>Data/Hora</th>
            <th>Usuário</th>
            <th>Documento Afetado</th>
            <th>Ação Realizada</th>
            <th>IP de Acesso</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result_logs->num_rows > 0): ?>
            <?php while($log = $result_logs->fetch_assoc()): ?>
                <tr>
                    <td><?= $log['id_log'] ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($log['data_acesso'])) ?></td>
                    <td><?= htmlspecialchars($log['nome_usuario']) ?></td>
                    <td><?= htmlspecialchars($log['nome_arquivo']) ?></td>
                    <td><span style="font-weight: bold; color: #1e88e5;"><?= htmlspecialchars($log['acao_log']) ?></span></td>
                    <td><?= htmlspecialchars($log['ip_acesso']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Nenhum registro de log encontrado.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
include 'footer.php';
$conn->close();
?>