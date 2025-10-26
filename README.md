👷 Sistema de Gestão de Colaboradores SESMT
📋 Sobre o Projeto
Este projeto consiste em um sistema de Dashboard e Listagem de Colaboradores desenvolvido em PHP com MySQLi (consultas preparadas), projetado para auxiliar o setor de SESMT (Serviços Especializados em Engenharia de Segurança e em Medicina do Trabalho) na organização e gestão de seu quadro funcional.

O objetivo principal é fornecer uma visão rápida (KPIs) e ferramentas de filtro para o acompanhamento dos colaboradores, sendo a base fundamental para a conformidade legal (NRs) e a gestão da saúde e segurança ocupacional.

✨ Funcionalidades Principais
O sistema oferece uma interface de gestão essencial para o SESMT:

Dashboard de KPIs:

Visualização imediata do total geral de colaboradores.

Contagem segregada de colaboradores Ativos e Inativos.

Total de colaboradores encontrados com os filtros aplicados.

Lista de Últimos Registros:

Exibe os 10 últimos colaboradores admitidos, permitindo uma ação rápida de edição para ajustes iniciais ou conferência de dados.

Filtros Avançados:

Permite filtrar a lista de colaboradores por:

Setor (para análise de riscos específicos da área).

Função (para mapeamento de GHE - Grupo Homogêneo de Exposição).

Status (Ativo/Inativo/Todos).

Listagem Detalhada:

Exibe a lista completa de colaboradores com dados cruciais (ID, Matrícula, Admissão, Setor, Função, Situação e Status).

Tecnologia Segura:

Utiliza Consultas Preparadas (Prepared Statements) com mysqli para garantir a segurança contra injeção de SQL.

⚙️ Tecnologias Utilizadas
Linguagem Backend: PHP

Banco de Dados: MySQL/MariaDB

Driver de Conexão: MySQLi (Orientado a Objetos)

Frontend: HTML/CSS (com classes de estilo kpi-card, data-table, etc., sugerindo o uso de um framework leve ou estilos customizados)

🏗️ Estrutura do Código
O código está organizado para promover a legibilidade e a separação de responsabilidades:

conexao.php: Lógica de conexão com o banco de dados.

header.php / footer.php: Inclusão de código HTML comum (estrutura de página, menus, rodapé).

dashboard_colaboradores.php (arquivo atual):

Busca de dados mestres (Setores, Funções, Situações).

Cálculo dos KPIs de Ativos/Inativos.

Execução de consultas com filtros via Prepared Statements (tratamento correto de bind_param dinâmico).

Estrutura de apresentação dos dados em HTML.

🚀 Como Executar o Projeto
Pré-requisitos: Servidor Web (Apache/Nginx) com PHP (versão 7.4+ recomendado) e MySQL.

Configuração do Banco:

Crie um banco de dados e importe as tabelas necessárias (colaboradores, setores, funcoes, situacao).

Configure as credenciais de acesso no arquivo conexao.php.

Deploy: Coloque todos os arquivos na raiz do seu servidor web (ou em um subdiretório configurado).

Acesso: Acesse o arquivo dashboard_colaboradores.php no seu navegador.

http://localhost/seu_projeto/dashboard_colaboradores.php
Desenvolvido com foco na organização e na conformidade da Segurança e Medicina do Trabalho.
