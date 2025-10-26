<?php
include 'conexao.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sql = "DELETE FROM atestados WHERE id_atestado = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redireciona de volta para a página principal após a exclusão
        header("Location: index.php");
        exit();
    } else {
        echo "Erro ao deletar atestado: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "ID do atestado não fornecido.";
}

$conn->close();
?>
