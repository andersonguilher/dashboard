# ✈️ Dashboard Completo de Operações de Voo (PHP/MySQL)

<img width="1615" height="925" alt="image" src="https://github.com/user-attachments/assets/4bde499d-b428-4d33-a177-f02ee7cf43fb" />

## 🎯 Visão Geral do Projeto

Este projeto é um painel de controle (*dashboard*) dinâmico e robusto, desenvolvido em **PHP** e **JavaScript**, ideal para o monitoramento e análise de operações de uma Companhia Aérea Virtual (VA) ou comunidade de simulação de voo.

Ele oferece uma visão de 360 graus da sua operação, abrangendo **indicadores-chave de desempenho (KPIs)**, **estatísticas detalhadas de pilotos**, e uma **análise financeira completa** da frota e dos voos. A configuração é flexível e intuitiva, realizada através de um painel administrativo.

-----

## ✨ Módulos e Funcionalidades Principais

O sistema é dividido em quatro módulos principais para garantir a segregação e clareza das informações:

### 📊 1. Dashboard Operacional (`index.php`)

O coração do sistema, fornecendo uma visão geral da atividade de voo:

  * **KPIs em Destaque:** Exibe o total de horas voadas e o número acumulado de voos da companhia.
  * **Voos Recentes:** Uma tabela atualizada em tempo real com os últimos 10 voos, indicando a rota, aeronave, piloto e a rede de simulação (IVAO/VATSIM).
  * **Piloto Destaque da Semana:** Reconhecimento dos pilotos com mais horas em categorias específicas de aeronaves (Leve, Médio, Pesado).
  * **Gráficos de Tendência:**
      * **Horas Acumuladas no Mês:** Acompanhamento da progressão das horas voadas no mês atual comparado ao mês anterior (Gráfico Acumulativo).
      * **Horas de Voo (Últimos 7 Dias):** Análise do desempenho diário de voo na semana atual versus a semana anterior.
      * **Top 5 Pilotos:** Ranking histórico dos pilotos com mais horas registradas.
  * **Interatividade (Hover):** Ao passar o mouse sobre um voo, exibe um card resumo das estatísticas do piloto, incluindo seu mini-gráfico de desempenho mensal.

### 💰 2. Dashboard Financeiro (`/financial`)

Foco total na saúde econômica da companhia, calculando custos, receitas e lucros:

  * **KPIs Financeiros:** Receita e Lucro Mensal, com comparação percentual em relação ao mês anterior.
  * **Evolução Anual:** Gráfico que rastreia a evolução anual de Receitas, Lucros e a discriminação de diferentes categorias de custos (Combustível, Manutenção, Operacional, Taxas).
  * **Performance da Frota:**
      * Ranking das 5 aeronaves mais lucrativas.
      * Tabela completa da frota operacional com recurso de pesquisa.
      * **Relatório por Modelo (`relatorio_aeronave.php`):** Página dedicada com análise financeira profunda para um modelo específico (receita, custos detalhados e evolução mensal do lucro).

### 🧑‍✈️ 3. Estatísticas de Pilotos (`est.php` e `estatisticas_piloto.php`)

Gerenciamento e análise individual do desempenho do corpo de pilotos:

  * **Status Geral:** Painel para visualizar o status de todos os pilotos e filtrar por aqueles em **Alerta de Inatividade** (pilotos que não voam há mais de 15 dias).
  * **Perfil Individual:** Página de estatísticas completa para cada piloto:
      * Resumo de horas totais, voos, tempo médio e aeronave principal.
      * Filtros por rede de simulação (Geral, IVAO, VATSIM).
      * Ranking dos aeroportos mais utilizados (Origens e Destinos).
      * Gráfico de horas acumuladas no mês e histórico dos últimos voos.

### ⚙️ 4. Painel de Administração (`/admin`)

Interface centralizada para personalizar o sistema sem necessidade de editar o código-fonte:

  * **Aparência:** Alteração rápida do tema de cores (Padrão, Escuro, Oceano, Vermelho).
  * **Idioma:** Definição do idioma padrão da interface (Português ou Espanhol).
  * **Mapeamento de Banco de Dados:** Recurso vital que permite configurar os nomes da tabela de pilotos e de suas respectivas colunas. Isso garante **compatibilidade com diferentes estruturas de bancos de dados** legados de outras VAs.

-----

## 🛠️ Tecnologias Utilizadas

| Categoria | Tecnologia | Uso |
| :--- | :--- | :--- |
| **Backend** | PHP 8+ | Lógica de negócios e conexão com o banco de dados. |
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla) | Interface de usuário e interatividade. |
| **Banco de Dados** | MySQL / MariaDB | Armazenamento dos dados de pilotos e voos. |
| **Gráficos** | [Chart.js](https://www.chartjs.org/) | Geração dos gráficos dinâmicos de performance. |
| **Ícones** | [Font Awesome](https://fontawesome.com/) | Ícones para melhor experiência visual. |

-----

## 🚀 Guia de Instalação e Configuração

Siga os passos abaixo para colocar o projeto em funcionamento em seu ambiente.

### Pré-requisitos

Certifique-se de que seu servidor web atende aos seguintes requisitos:

1.  **Servidor Web:** Apache, Nginx, ou similar.
2.  **PHP:** Versão **8.0** ou superior com a extensão `mysqli` habilitada.
3.  **Banco de Dados:** Servidor MySQL ou MariaDB.

### 1\. Clone o Repositório

Baixe o projeto para a pasta raiz do seu servidor web (ex: `/var/www/html/`).

```bash
git clone <URL_DO_SEU_REPOSITORIO>
cd <NOME_DA_PASTA_DO_PROJETO>
```

### 2\. Configure o Banco de Dados (Estrutura)

O sistema requer **dois bancos de dados separados**: um para os dados dos pilotos e outro para o registro de voos e da frota.

**a. Crie os Bancos de Dados:**
Use nomes de sua preferência. Por exemplo, `sua_va_pilotos` e `sua_va_voos`.

```sql
CREATE DATABASE `sua_va_pilotos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `sua_va_voos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**b. Crie as Tabelas:**
Execute as queries abaixo no banco de dados correspondente.

  - **No banco de dados de PILOTOS (`sua_va_pilotos`):**

    ```sql
    CREATE TABLE `Dados_dos_Pilotos` (
      `id_piloto` INT AUTO_INCREMENT PRIMARY KEY,
      `post_id` INT,
      `first_name` VARCHAR(100),
      `last_name` VARCHAR(100),
      `vatsim_id` VARCHAR(20) UNIQUE,
      `ivao_id` VARCHAR(20) UNIQUE,
      `foto_perfil` VARCHAR(255),
      `validado` VARCHAR(10) DEFAULT 'true',
      `matricula` VARCHAR(10)
    );
    ```

  - **No banco de dados de VOOS (`sua_va_voos`):**

    ```sql
    CREATE TABLE `voos` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `userId` VARCHAR(20),
      `time` INT,
      `peopleOnBoard` INT,
      `flightPlan_aircraft_model` VARCHAR(50),
      `createdAt` DATETIME,
      `flightPlan_departureId` VARCHAR(4),
      `flightPlan_arrivalId` VARCHAR(4),
      `wakeTurbulence` CHAR(1),
      `callsign` VARCHAR(20),
      `network` CHAR(1)
    );

    CREATE TABLE `frota` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `registration` VARCHAR(10) NOT NULL UNIQUE,
      `model` VARCHAR(50) NOT NULL,
      `category` CHAR(1),
      `operational_cost_per_hour` DECIMAL(10, 2),
      `maintenance_per_hour` DECIMAL(10, 2),
      `fuel_consumption_per_hour` DECIMAL(10, 2),
      `revenue_per_pax_per_hour` DECIMAL(10, 2),
      `min_revenue_per_pax` DECIMAL(10, 2),
      `min_flight_duration` DECIMAL(10, 2)
    );
    ```

**c. Crie um Usuário (Opcional, mas recomendado):**
Para maior segurança, crie um usuário com permissões para ambos os bancos.

```sql
CREATE USER 'seu_usuario'@'localhost' IDENTIFIED BY 'sua_senha';
GRANT ALL PRIVILEGES ON `sua_va_pilotos`.* TO 'seu_usuario'@'localhost';
GRANT ALL PRIVILEGES ON `sua_va_voos`.* TO 'seu_usuario'@'localhost';
FLUSH PRIVILEGES;
```

### 3\. Configure a Conexão

Crie um arquivo chamado `config_db.php` **dois níveis acima** da pasta raiz do projeto (ex: se o projeto está em `/var/www/html/dashboard`, o arquivo deve estar em `/var/www/config_db.php`). Esta localização externa aumenta a segurança. Cole o conteúdo abaixo e ajuste as credenciais com os nomes dos bancos de dados e usuários que você criou.

```php
<?php
// =================================================================
// ARQUIVO DE CONFIGURAÇÃO SEGURA DO BANCO DE DADOS
// =================================================================

define('DB_SERVERNAME', 'localhost');

// --- Credenciais para o banco de dados de PILOTOS ---
define('DB_PILOTOS_NAME', 'sua_va_pilotos'); // <-- AJUSTE AQUI
define('DB_PILOTOS_USER', 'seu_usuario');   // <-- AJUSTE AQUI
define('DB_PILOTOS_PASS', 'sua_senha');     // <-- AJUSTE AQUI

// --- Credenciais para o banco de dados de VOOS ---
define('DB_VOOS_NAME', 'sua_va_voos');   // <-- AJUSTE AQUI
define('DB_VOOS_USER', 'seu_usuario');   // <-- AJUSTE AQUI
define('DB_VOOS_PASS', 'sua_senha');     // <-- AJUSTE AQUI

/**
 * Função para criar uma conexão com o banco de dados de forma segura.
 * @param string $dbName - O nome do banco de dados.
 * @param string $dbUser - O nome de usuário do banco.
 * @param string $dbPass - A senha do banco.
 * @return mysqli|null - Retorna o objeto de conexão mysqli em caso de sucesso ou null em caso de falha.
 */
function criar_conexao($dbName, $dbUser, $dbPass) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        $conn = new mysqli(DB_SERVERNAME, $dbUser, $dbPass, $dbName);
        $conn->set_charset("utf8");
        return $conn;

    } catch (mysqli_sql_exception $e) {
        // Exibe uma mensagem de erro genérica para o usuário.
        die("Falha na conexão com o banco de dados. Por favor, tente novamente mais tarde.");
    }
}
?>
```

### 4\. Insira Dados de Exemplo (Opcional)

Use as queries abaixo para preencher o sistema com dados iniciais de pilotos e voos, permitindo que você visualize o dashboard imediatamente após a instalação.

  - **Inserir Pilotos de Exemplo (no banco de dados de PILOTOS):**

<!-- end list -->

```sql
INSERT INTO `Dados_dos_Pilotos` 
    (`post_id`, `first_name`, `last_name`, `vatsim_id`, `ivao_id`, `foto_perfil`, `validado`, `matricula`) 
VALUES
    (1, 'João', 'Silva', '323734', NULL, 'assets/images/joao.png', 'true', 'KFY001'),       -- ID Vatsim com voos existentes (ativo)
    (2, 'Maria', 'Santos', NULL, '257417', 'assets/images/maria.png', 'true', 'KFY002'),    -- ID IVAO com voos existentes (ativo)
    (3, 'Pedro', 'Costa', '112233', '445566', 'assets/images/pedro.png', 'true', 'KFY003'), -- Novo piloto (validado, para testar inatividade/alerta)
    (4, 'Ana', 'Oliveira', '998877', NULL, 'assets/images/ana.png', 'false', 'KFY004');    -- Piloto não validado (para testes de status)
```

  - **Inserir Voos de Exemplo (no banco de dados de VOOS):**
    *(Nota: Esta query usa a coluna `primare_key` e outros campos presentes em seu arquivo original para inserção. Se você usou a query `CREATE TABLE` do passo 2, o campo `primare_key` deve ser substituído por `id` ou a query de criação deve ser ajustada para incluir `primare_key`.)*

<!-- end list -->

```sql
-- Despejando os 10 primeiros dados para a tabela `voos`
INSERT INTO `voos` (`primare_key`, `time`, `createdAt`, `mes`, `id`, `userId`, `flightPlan_departureId`, `flightPlan_arrivalId`, `flightPlan_aircraft_model`, `callsign`, `peopleOnBoard`, `wakeTurbulence`, `remarks`, `network`) VALUES
(1, 26439, '2023-06-21 02:15:00', 6, '5bff756a6c8220274850fb9dd28da3083654557e8d71365f0db1373ee6c79bd9', '708296', 'LPPT', 'TBPB', 'A310', 'KFY1022', '154', 'H', '', 'i'),
(2, 2040, '2023-06-20 19:53:00', 6, 'cbe000c5892466d86b4de94a131e684235685ce9fc12de7ac38c4c0b427e9e21', '257417', 'ENGM', 'ESGG', '757-300', 'KFY1001', '249', 'H', '', 'i'),
(3, 6300, '2023-06-20 18:38:00', 6, '122cb868a0a024f114f3373cf1a594cf3ac2d5a57786a4e84a66fdfe95cda3da', '424408', 'SEQM', 'SKBO', 'xxxx', 'KFY1025', '', '', '', 'i'),
(4, 8640, '2023-06-20 17:25:00', 6, 'c4fe9773d704f8d0834eb89141711360435e49ee44e03c1a06445fdcdaf70f7c', '257417', 'EGNX', 'ENGM', 'xxxx', 'KFY1001', '', '', '', 'i'),
(5, 2460, '2023-06-20 15:44:00', 6, '18b68f94591d0c995e2ffa8ebb635bdea1d76a232c4669d18666470e8c6ef79e', '310791', '3DW ', ' 2MO', 'xxxx', 'KFY1039', '', '', '', 'i'),
(6, 1800, '2023-06-20 15:13:00', 6, '18b68f94591d0c995e2ffa8ebb635bdea1d76a232c4669d18666470e8c6ef79e', '310791', 'KLBO', ' 3DW', 'xxxx', 'KFY1039', '', '', '', 'i'),
(7, 1740, '2023-06-20 12:11:00', 6, '18b68f991d0c995e2ffa8ebb635bdea1d76a232c4669d18666470e8c6ef79e', '310791', 'K07 ', 'KLBO', 'xxxx', 'KFY1039', '', '', '', 'i'),
(8, 1500, '2023-06-20 11:45:00', 6, '18b68f94591d0c995e2ffa8ebb635bdea1d76a232c4669d18666470e8c6ef79e', '310791', 'KUUV', ' K07', 'xxxx', 'KFY1039', '', '', '', 'i'),
(9, 3300, '2023-06-20 04:31:00', 6, '69e7be05b13101966b046979bc0df9e9cecfc118a15448154c27fb9b82f1adf0', '314237', 'ZPJH', 'VVDB', 'xxxx', 'KFY1520', '', '', '', 'i'),
(10, 4980, '2023-06-20 01:44:00', 6, 'cb8942056e3c9af133bd4410ae31edbce091ad1039a8b809baa90c2aeebac6b0', '203220', 'FALE', 'FAGM', 'xxxx', 'KFY1015', '', '', '', 'i');
```

### 5\. Configure as Permissões

O painel administrativo (`/admin`) precisa de permissão de escrita para o arquivo `config/settings.json` para salvar as configurações de tema, idioma e mapeamento das colunas.

Execute os comandos a seguir na pasta raiz do projeto (exemplo para Linux):

```bash
# Exemplo em um servidor Linux (execute na pasta raiz do projeto)
chmod 664 config/settings.json
chown www-data:www-data config/settings.json
```

### 6\. Acesse o Painel

Após concluir os passos, acesse o projeto pelo seu navegador. Para configurar o sistema, navegue até a pasta `/admin`.

-----

## 🤖 Utilitário de Banco de Dados (Opcional)

O projeto inclui um `Stored Procedure` opcional para automatizar o preenchimento da tabela `frota` com base nos modelos de aeronaves que seus pilotos já voaram, atribuindo custos e receitas realistas.

### O que ele faz?

  - **Analisa a tabela `voos`**: Identifica todos os modelos de aeronaves únicos que já realizaram voos.
  - **Verifica a frota existente**: Para cada modelo, conta quantas aeronaves já existem na tabela `frota`.
  - **Gera novas aeronaves**: Se o número de aeronaves for menor que um valor pré-definido por categoria (Leve, Médio, Pesado), ele insere novas aeronaves até atingir o mínimo.
  - **Cria dados realistas**: Gera matrículas aleatórias e atribui custos e receitas com uma leve variação para cada aeronave, tornando a frota mais dinâmica.

### Como usar?

**a. Crie o Stored Procedure:**
Execute o código SQL abaixo no seu **banco de dados de voos** (`sua_va_voos`). Você só precisa fazer isso uma vez.

```sql
DELIMITER $$
CREATE PROCEDURE `sp_verificar_e_inserir_frota`()
BEGIN
    DECLARE v_model VARCHAR(50);
    DECLARE v_category CHAR(1);
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_required_count INT;
    DECLARE v_current_count INT;
    DECLARE v_to_insert_count INT;
    DECLARE i INT;
    DECLARE v_exists INT;
    DECLARE v_new_registration VARCHAR(10);
    DECLARE v_three_letters CHAR(3);
    DECLARE v_new_cost DECIMAL(10,2);
    DECLARE v_new_maint DECIMAL(10,2);
    DECLARE v_new_fuel DECIMAL(10,2);
    DECLARE v_new_revenue_per_pax_hr DECIMAL(10,2);
    DECLARE v_min_revenue_per_pax DECIMAL(10,2);
    DECLARE v_min_flight_duration DECIMAL(10,2);

    DECLARE aircraft_cursor CURSOR FOR
        SELECT flightPlan_aircraft_model, wakeTurbulence 
        FROM voos
        WHERE flightPlan_aircraft_model IS NOT NULL 
          AND flightPlan_aircraft_model <> '' 
          AND flightPlan_aircraft_model NOT LIKE '%xx%' 
          AND wakeTurbulence IN ('L','M','H')
        GROUP BY flightPlan_aircraft_model, wakeTurbulence;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    OPEN aircraft_cursor;

    read_loop: LOOP
        FETCH aircraft_cursor INTO v_model, v_category;
        IF v_done THEN LEAVE read_loop; END IF;

        SET v_required_count = CASE v_category 
                                 WHEN 'L' THEN 4 
                                 WHEN 'M' THEN 10 
                                 WHEN 'H' THEN 10 
                                 ELSE 0 END;

        SELECT COUNT(*) INTO v_current_count FROM frota WHERE model = v_model;
        SET v_to_insert_count = v_required_count - v_current_count;

        IF v_to_insert_count > 0 THEN
            -- Define os parâmetros financeiros baseados na categoria
            SET v_new_revenue_per_pax_hr = CASE v_category WHEN 'L' THEN 320.00 WHEN 'M' THEN 290.00 WHEN 'H' THEN 390.00 ELSE 150.00 END;
            SET v_min_revenue_per_pax = CASE v_category WHEN 'L' THEN 400.00 WHEN 'M' THEN 300.00 WHEN 'H' THEN 400.00 ELSE 150.00 END;
            SET v_min_flight_duration = CASE v_category WHEN 'L' THEN 1.0 WHEN 'M' THEN 1.5 WHEN 'H' THEN 2.0 ELSE 0.5 END;

            SET i = 0;
            WHILE i < v_to_insert_count DO
                -- Gera uma matrícula única
                uniqueness_loop:LOOP
                    SET v_three_letters = CONCAT(CHAR(FLOOR(65 + RAND()*26)), CHAR(FLOOR(65 + RAND()*26)), CHAR(FLOOR(65 + RAND()*26)));
                    SET v_new_registration = CONCAT('PR-', v_three_letters);
                    SELECT COUNT(*) INTO v_exists FROM frota WHERE registration = v_new_registration;
                    IF v_exists = 0 THEN LEAVE uniqueness_loop; END IF;
                END LOOP uniqueness_loop;

                -- Gera custos e consumo com variação
                SET v_new_cost = CASE v_category WHEN 'L' THEN 700 * (1 + (RAND()-0.5)*0.1) WHEN 'M' THEN 4500 * (1 + (RAND()-0.5)*0.1) WHEN 'H' THEN 10000 * (1 + (RAND()-0.5)*0.1) ELSE 2000 END;
                SET v_new_maint = v_new_cost * 0.2;
                SET v_new_fuel = CASE v_category WHEN 'L' THEN 130 + (RAND()-0.5)*20 WHEN 'M' THEN 2850 + (RAND()-0.5)*150 WHEN 'H' THEN 7000 + (RAND()-0.5)*500 ELSE 500 + (RAND()-0.5)*100 END;

                -- Insere a nova aeronave
                INSERT INTO frota (registration, model, category, operational_cost_per_hour, maintenance_per_hour, fuel_consumption_per_hour, revenue_per_pax_per_hour, min_revenue_per_pax, min_flight_duration)
                VALUES (v_new_registration, v_model, v_category, v_new_cost, v_new_maint, v_new_fuel, v_new_revenue_per_pax_hr, v_min_revenue_per_pax, v_min_flight_duration);

                SET i = i + 1;
            END WHILE;
        END IF;
    END LOOP;

    CLOSE aircraft_cursor;
END$$
DELIMITER ;
```

**b. Execute o Procedure:**
Sempre que quiser atualizar sua frota, basta executar o seguinte comando SQL:

```sql
CALL sp_verificar_e_inserir_frota();
```

-----

## 📁 Estrutura do Projeto

```
.
├── admin/                     # Painel de configuração global (tema, idioma, mapeamento de DB)
├── assets/                    # Imagens, logos e recursos estáticos
├── config/                    # Arquivos de configuração e idiomas
│   ├── lang/                  # Arquivos de tradução (pt.php, es.php)
│   └── settings.json          # Configurações dinâmicas do sistema
├── financial/                 # Módulo do Dashboard Financeiro
│   ├── index.php              # Dashboard Financeiro Principal
│   └── relatorio_aeronave.php # Relatório detalhado por aeronave
├── src/                       # Lógica principal e carregadores
│   └── config_loader.php      # Carrega configurações globais e tema
├── est.php                    # Página de status geral dos pilotos (filtros de alerta)
├── estatisticas_piloto.php    # Página de estatísticas individuais do piloto
└── index.php                  # Página principal do Dashboard Operacional
```
