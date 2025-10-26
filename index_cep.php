<?php
// Arquivo: index.php

// A classe ServiceAPI foi mantida aqui por referência, mas não é usada no frontend.
// A chamada da API agora é feita via AJAX para api_cep.php
class ServiceAPI {
    private $key = '18639a6f8539f1e821ed4ea22599d74b';

    function request($uri, $type_request) {
        // ... (código original da classe) ...
    }

    function getCep(){
        // ... (código original da classe) ...
    }

    function getAddress(){
        // ... (código original da classe) ...
    }

    function getCities(){
        // ... (código original da classe) ...
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de Endereço</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 600px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Formulário de Inserção de Endereço</h1>
        <form id="form-endereco">
            <div class="form-group">
                <label for="cep">CEP:</label>
                <input type="text" id="cep" name="cep" maxlength="9">
            </div>
            <div class="form-group">
                <label for="logradouro">Rua:</label>
                <input type="text" id="logradouro" name="logradouro">
            </div>
            <div class="form-group">
                <label for="bairro">Bairro:</label>
                <input type="text" id="bairro" name="bairro">
            </div>
            <div class="form-group">
                <label for="localidade">Cidade:</label>
                <input type="text" id="localidade" name="localidade">
            </div>
            <div class="form-group">
                <label for="uf">Estado:</label>
                <input type="text" id="uf" name="uf">
            </div>
            <button type="submit">Enviar</button>
        </form>
    </div>

    <script>
        document.getElementById('cep').addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');

            if (cep.length !== 8) {
                return;
            }

            fetch(`api_cep.php?cep=${cep}`)
                .then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                    throw new Error('Erro na requisição');
                })
                .then(data => {
                    if (data.logradouro) {
                        document.getElementById('logradouro').value = data.logradouro || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('localidade').value = data.cidade || ''; // A API retorna 'cidade'
                        document.getElementById('uf').value = data.estado || '';      // A API retorna 'estado'
                    } else {
                        alert('CEP não encontrado.');
                        // Limpar os campos
                        document.getElementById('logradouro').value = '';
                        document.getElementById('bairro').value = '';
                        document.getElementById('localidade').value = '';
                        document.getElementById('uf').value = '';
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    alert('Erro ao consultar o CEP.');
                });
        });
    </script>
</body>
</html>
