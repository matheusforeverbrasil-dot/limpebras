<?php
// Arquivo: delete_treinamento.php
include 'config_treinamento.php';

$mensagem = '';
if (isset($_GET['id_treinamento'])) {
    $id = $_GET['id_treinamento'];

    $sql = "DELETE FROM treinamentos WHERE id_treinamento = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $mensagem = "Treinamento excluído com sucesso!";
    } else {
        $mensagem = "Erro ao excluir treinamento: " . $conexao->error;
    }
    $stmt->close();
} else {
    $mensagem = "ID do treinamento não especificado.";
}

$conexao->close();
header("Location: index_treinamento.php?mensagem=" . urlencode($mensagem));
exit;
