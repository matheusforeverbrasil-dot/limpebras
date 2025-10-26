<?php
include 'conexao.php';

if (isset($_GET['id'])) {
    $id_atestado = (int)$_GET['id'];

    $sql = "SELECT caminho_pdf FROM atestados WHERE id_atestado = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_atestado);
    $stmt->execute();
    $stmt->bind_result($pdf_blob);
    $stmt->fetch();
    $stmt->close();

    if ($pdf_blob) {
        header('Content-Type: application/pdf');
        echo $pdf_blob;
    } else {
        echo "Arquivo não encontrado.";
    }
} else {
    echo "ID não especificado.";
}

$conn->close();
?>
