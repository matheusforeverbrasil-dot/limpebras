<?php
// Inclua seu arquivo de conexão, se necessário, para buscar o caminho do PDF
include 'conexao.php';

// Verifique se o ID do atestado foi passado via GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];

    // Busque o caminho do arquivo PDF no banco de dados
    $sql = "SELECT caminho_pdf FROM atestados WHERE id_atestado = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filepath = $row['caminho_pdf'];
        $filepath = __DIR__ . "/" . $filepath; // Constrói o caminho completo

        // Verifique se o arquivo existe
        if (file_exists($filepath)) {
            // Limpa o buffer de saída para evitar corrupção
            ob_clean();
            
            // Define os cabeçalhos HTTP para forçar o download do arquivo
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));

            // Lê o arquivo e o envia para o navegador
            readfile($filepath);
            
            // Encerra o script para garantir que nenhuma saída adicional seja enviada
            exit;
        } else {
            // Se o arquivo não existir
            http_response_code(404);
            die('Arquivo não encontrado.');
        }
    } else {
        // Se o atestado não for encontrado
        http_response_code(404);
        die('Atestado não encontrado no banco de dados.');
    }
} else {
    // Se nenhum ID válido for fornecido
    http_response_code(400);
    die('ID de atestado inválido.');
}
?>
