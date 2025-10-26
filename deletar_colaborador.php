<?php
include 'conexao.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Verificar se há atestados vinculados a este colaborador
    $sql_check = "SELECT COUNT(*) FROM atestados WHERE colaborador_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        die("Não é possível deletar este colaborador, pois ele possui atestados vinculados. Remova os atestados primeiro.");
    }

    // Se não houver atestados, proceder com a exclusão
    $sql = "DELETE FROM colaboradores WHERE id_colaborador = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: index_colaboradores.php");
        exit();
    } else {
        echo "Erro ao deletar colaborador: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "ID do colaborador não fornecido.";
}

$conn->close();
?>
