# ✈️ Dashboard Completo de Operações de Voo (PHP/MySQL)

<img width="1615" height="925" alt="image" src="https://github.com/user-attachments/assets/4bde499d-b428-4d33-a177-f02ee7cf43fb" />

## 🎯 Visão Geral do Projeto

Este projeto é um painel de controle (*dashboard*) dinâmico e robusto, desenvolvido em **PHP** e **JavaScript**, ideal para o monitoramento e análise de operações de uma Companhia Aérea Virtual (VA) ou comunidade de simulação de voo.

Ele oferece uma visão de 360 graus da sua operação, abrangendo **indicadores-chave de desempenho (KPIs)**, **estatísticas detalhadas de pilotos com sistema de alerta de inatividade e notificação por e-mail**, e uma **análise financeira completa** da frota e dos voos. A configuração é flexível e intuitiva, realizada através de um painel administrativo, que permite inclusive o mapeamento de colunas de banco de dados para compatibilidade.

---

## ✨ Módulos e Funcionalidades Principais

O sistema é dividido em módulos principais para garantir a segregação e clareza das informações:

### 🖥️ Home Page / Landing Page (`home/index.php`)

Uma interface inicial limpa e leve, ideal para ser a página de entrada principal da VA, com recursos dinâmicos:

* **Menu e Header Fixo:** Navegação configurável via painel admin, com links corrigidos e logo dinâmico.
* **KPI Bar:** Exibição em destaque das Horas Totais, Voos Totais e Pilotos Ativos.
* **Pilot Hover Card:** Ao passar o mouse sobre o nome do piloto nos voos recentes, exibe um resumo estatístico em tempo real, incluindo o mini-gráfico de desempenho mensal.
* **Gadgets e Mapa:** Seção de widgets informativos e visualização do mapa de operações 3D (via Iframe).

### 📊 Dashboard Operacional (`index.php`)

O coração do sistema, fornecendo uma visão geral da atividade de voo:

* **KPIs em Destaque:** Exibe o total de horas voadas e o número acumulado de voos da companhia.
* **Voos Recentes:** Tabela atualizada com os últimos 10 voos, incluindo informações de desempenho de pouso e combustível e o **Pilot Hover Card**.
* **Piloto Destaque da Semana:** Reconhecimento dos pilotos com mais horas na semana anterior por categoria de aeronave (Leve, Médio, Pesado).
* **Ranking de Pouso:** Exibe o TOP 3 Pousos de melhor desempenho (`landing_vs` mais próximo de 0) da semana atual, por categoria (L, M, H), com feedback visual por cor (Verde para suave, Vermelho para duro).
* **Gráficos de Tendência:** Baseados em **GMT/UTC** para precisão internacional.

  * Horas Acumuladas no Mês (Acumulativo).
  * Horas de Voo (Últimos 7 Dias) comparado à semana anterior.
  * Top 5 Pilotos (Ranking histórico).

### 💰 Dashboard Financeiro (`/financial`)

Foco total na saúde econômica da companhia, calculando custos, receitas e lucros:

* **KPIs Financeiros:** Receita, Lucro e Passageiros Mensais, com comparação percentual em relação ao mês anterior.
* **Evolução Anual:** Gráfico de tendência anual de Receitas e Lucros. Inclui **controle de botões** para alternar a visualização dos custos (Total, Combustível, Manutenção, Operacional, Taxas).
* **Performance da Frota:**

  * Tabela completa da frota operacional com **campo de pesquisa** por modelo e **cabeçalho fixo**.
  * **Relatório por Modelo (`relatorio_aeronave.php`):** Página detalhada com **KPIs de Custo e Receita totais** e um gráfico de **Evolução do Lucro Mensal**.

### 🧑‍✈️ Estatísticas de Pilotos (`est.php` e `estatisticas_piloto.php`)

Gerenciamento e análise individual do desempenho do corpo de pilotos:

* **Alerta de Inatividade:** Painel que filtra pilotos em **Alerta de Inatividade** (registrados há mais de 29 dias e inativos há mais de 15 dias).

  * **Ação de Desabilitação:** Permite **desabilitar** o piloto via formulário de confirmação (**Modal**) e **enviar um e-mail de notificação**.
* **Perfil Individual (`estatisticas_piloto.php`):** Exibe o resumo de horas, voos, aeronave principal, e rankings de aeroportos.

### ⚙️ Painel de Administração (`/admin`)

Interface centralizada para personalizar o sistema:

* **Configurações da Companhia:** Permite definir o nome e o **e-mail da companhia** (usado no e-mail de notificação de inatividade).
* **Mapeamento de Banco de Dados:** Recurso vital para mapear colunas personalizadas, incluindo a coluna **`email_piloto`**.

---

## 🛠️ Tecnologias Utilizadas

| Categoria          | Tecnologia                               | Uso                                                |
| :----------------- | :--------------------------------------- | :------------------------------------------------- |
| **Backend**        | PHP 8+                                   | Lógica de negócios e conexão com o banco de dados. |
| **Frontend**       | HTML5, CSS3, JavaScript (Vanilla)        | Interface de usuário e interatividade.             |
| **Banco de Dados** | MySQL / MariaDB                          | Armazenamento dos dados de pilotos e voos.         |
| **Gráficos**       | [Chart.js](https://www.chartjs.org/)     | Geração dos gráficos dinâmicos de performance.     |
| **Ícones**         | [Font Awesome](https://fontawesome.com/) | Ícones para melhor experiência visual.             |

---

## 🚀 Guia de Instalação e Configuração

### Pré-requisitos

1. **Servidor Web:** Apache, Nginx ou similar.
2. **PHP:** Versão 8.0+ com extensão `mysqli` habilitada.
3. **Banco de Dados:** MySQL ou MariaDB.

### 1. Clone o Repositório

```bash
git clone <URL_DO_SEU_REPOSITORIO>
cd <NOME_DA_PASTA_DO_PROJETO>
```

### 2. Configure o Banco de Dados

**a. Crie os Bancos de Dados:**

```sql
CREATE DATABASE `sua_va_pilotos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `sua_va_voos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**b. Crie as Tabelas:**

*Banco de Pilotos (`sua_va_pilotos`)*

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
  `matricula` VARCHAR(10),
  `email_piloto` VARCHAR(150) NULL
);
```

*Banco de Voos (`sua_va_voos`)*

```sql
CREATE TABLE `voos` (
  `primare_key` INT AUTO_INCREMENT PRIMARY KEY,
  `time` INT,
  `createdAt` DATETIME,
  `mes` INT,
  `id` VARCHAR(64),
  `userId` VARCHAR(20),
  `flightPlan_departureId` VARCHAR(4),
  `flightPlan_arrivalId` VARCHAR(4),
  `flightPlan_aircraft_model` VARCHAR(50),
  `callsign` VARCHAR(20),
  `peopleOnBoard` INT,
  `wakeTurbulence` CHAR(1),
  `remarks` TEXT,
  `network` CHAR(1),
  `fuel_used` DECIMAL(10,2) DEFAULT 0.00,
  `landing_vs` DECIMAL(8,2) DEFAULT 0.00,
  `registration` VARCHAR(10) NULL
);

CREATE TABLE `frota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration` VARCHAR(10) NOT NULL UNIQUE,
  `model` VARCHAR(50) NOT NULL,
  `category` CHAR(1),
  `operational_cost_per_hour` DECIMAL(10,2),
  `maintenance_per_hour` DECIMAL(10,2),
  `fuel_consumption_per_hour` DECIMAL(10,2),
  `revenue_per_pax_per_hour` DECIMAL(10,2),
  `min_revenue_per_pax` DECIMAL(10,2),
  `min_flight_duration` DECIMAL(10,2)
);
```

**c. Crie um Usuário (Opcional)**

```sql
CREATE USER 'seu_usuario'@'localhost' IDENTIFIED BY 'sua_senha';
GRANT ALL PRIVILEGES ON `sua_va_pilotos`.* TO 'seu_usuario'@'localhost';
GRANT ALL PRIVILEGES ON `sua_va_voos`.* TO 'seu_usuario'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure a Conexão

Crie `config_db.php`:

```php
<?php
define('DB_SERVERNAME', 'localhost');
define('DB_PILOTOS_NAME', 'sua_va_pilotos');
define('DB_PILOTOS_USER', 'seu_usuario');
define('DB_PILOTOS_PASS', 'sua_senha');
define('DB_VOOS_NAME', 'sua_va_voos');
define('DB_VOOS_USER', 'seu_usuario');
define('DB_VOOS_PASS', 'sua_senha');

function criar_conexao($dbName, $dbUser, $dbPass) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(DB_SERVERNAME, $dbUser, $dbPass, $dbName);
        $conn->set_charset("utf8");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        die("Falha na conexão com o banco de dados.");
    }
}
?>
```

### 4. Insira Dados de Exemplo

**Pilotos:**

```sql
INSERT INTO `Dados_dos_Pilotos` 
(`post_id`, `first_name`, `last_name`, `vatsim_id`, `ivao_id`, `foto_perfil`, `validado`, `matricula`, `email_piloto`) VALUES
(1, 'João', 'Silva', '323734', NULL, 'assets/images/joao.png', 'true', 'KFY001', 'joao.silva@exemplo.com'),
(2, 'Maria', 'Santos', NULL, '257417', 'assets/images/maria.png', 'true', 'KFY002', 'maria.santos@exemplo.com');
```

**Voos:**

```sql
INSERT INTO `voos` (`primare_key`, `time`, `createdAt`, `mes`, `id`, `userId`, `flightPlan_departureId`, `flightPlan_arrivalId`, `flightPlan_aircraft_model`, `callsign`, `peopleOnBoard`, `wakeTurbulence`, `remarks`, `network`, `fuel_used`, `landing_vs`, `registration`) VALUES
(1, 26439, '2023-06-21 02:15:00', 6, 'hash1', '708296', 'LPPT', 'TBPB', 'A310', 'KFY1022', '154', 'H', '', 'i', 4500.00, -180.50, 'PR-JAB');
```

### 5. Permissões

```bash
chmod 664 config/settings.json
chown www-data:www-data config/settings.json
```

### 6. Acesse o Painel

Abra no navegador `/admin` para configuração.

### Stored Procedure para Frota

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
    
    -- Variáveis para Atribuição de Matrículas
    DECLARE reg_list TEXT;
    DECLARE reg_count INT;
    DECLARE flight_done INT DEFAULT FALSE;
    DECLARE v_flight_pk BIGINT;
    DECLARE v_reg_index INT DEFAULT 1;
    DECLARE v_reg_str VARCHAR(10);

    DECLARE aircraft_cursor CURSOR FOR
        SELECT flightPlan_aircraft_model, wakeTurbulence 
        FROM voos
        WHERE flightPlan_aircraft_model IS NOT NULL 
          AND flightPlan_aircraft_model <> '' 
          AND flightPlan_aircraft_model NOT LIKE '%xx%' 
          AND wakeTurbulence IN ('L','M','H')
        GROUP BY flightPlan_aircraft_model, wakeTurbulence;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    -- Inicializa a variável de sessão/usuário para uso no loop de atribuição
    SET @row_number = 0; 
    
    OPEN aircraft_cursor;

    read_loop: LOOP
        FETCH aircraft_cursor INTO v_model, v_category;
        IF v_done THEN LEAVE read_loop; END IF;

        -- CÁLCULO DINÂMICO DO REQUISITO DE FROTA (MÁXIMO SIMULTÂNEO)
        SELECT COALESCE(MAX(t.flight_count), 0) INTO v_required_count
        FROM (
            SELECT 
                DATE(createdAt) as flight_day,
                HOUR(createdAt) as flight_hour,
                COUNT(DISTINCT userId) as flight_count
            FROM voos
            WHERE flightPlan_aircraft_model = v_model
            GROUP BY flight_day, flight_hour
        ) as t;

        IF v_required_count = 0 THEN
            SET v_required_count = 1;
        END IF;

        -- Lógica de Inserção de Novas Matrículas
        SELECT COUNT(*) INTO v_current_count FROM frota WHERE model = v_model;
        SET v_to_insert_count = v_required_count - v_current_count;

        IF v_to_insert_count > 0 THEN
            -- Receita/hora base por categoria
            SET v_new_revenue_per_pax_hr = CASE v_category
                                             WHEN 'L' THEN 900.00
                                             WHEN 'M' THEN 305.00 
                                             WHEN 'H' THEN 390.00
                                             ELSE 150.00
                                           END;

            -- Valores mínimos de receita por passageiro em voos curtos
            SET v_min_revenue_per_pax = CASE v_category
                                          WHEN 'L' THEN 900.00
                                          WHEN 'M' THEN 305.00 
                                          WHEN 'H' THEN 400.00
                                          ELSE 150.00
                                        END;

            -- Duração mínima para aplicar receita normal (horas)
            SET v_min_flight_duration = CASE v_category
                                          WHEN 'L' THEN 1.0
                                          WHEN 'M' THEN 1.5
                                          WHEN 'H' THEN 2.0
                                          ELSE 0.5
                                        END;

            SET i = 0;
            WHILE i < v_to_insert_count DO
                -- Geração única de matrícula
                uniqueness_loop:LOOP
                    SET v_three_letters = CONCAT(CHAR(FLOOR(65 + RAND()*26)), CHAR(FLOOR(65 + RAND()*26)), CHAR(FLOOR(65 + RAND()*26)));
                    SET v_new_registration = CONCAT('PR-', v_three_letters);
                    SELECT COUNT(*) INTO v_exists FROM frota WHERE registration = v_new_registration;
                    IF v_exists = 0 THEN LEAVE uniqueness_loop; END IF;
                END LOOP uniqueness_loop;
                
                -- Custo Operacional e Manutenção
                SET v_new_cost = CASE v_category WHEN 'L' THEN 700 * (1 + (RAND()-0.5)*0.1) WHEN 'M' THEN 4500 * (1 + (RAND()-0.5)*0.1) WHEN 'H' THEN 10000 * (1 + (RAND()-0.5)*0.1) ELSE 2000 END;
                SET v_new_maint = v_new_cost * 0.2;
                
                -- CORREÇÃO CRÍTICA: Ajusta o consumo de combustível para Categoria 'L' para um valor realista (25 L/h)
                SET v_new_fuel = CASE v_category 
                                 WHEN 'L' THEN 25 + (RAND()-0.5)*5 -- Categoria Leve (C150)
                                 WHEN 'M' THEN 2850 + (RAND()-0.5)*150 
                                 WHEN 'H' THEN 7000 + (1 + (RAND()-0.5)*500) 
                                 ELSE 500 + (RAND()-0.5)*100 END;

                INSERT INTO frota (registration, model, category, operational_cost_per_hour, maintenance_per_hour, fuel_consumption_per_hour, revenue_per_pax_per_hour, min_revenue_per_pax, min_flight_duration)
                VALUES (v_new_registration, v_model, v_category, v_new_cost, v_new_maint, v_new_fuel, v_new_revenue_per_pax_hr, v_min_revenue_per_pax, v_min_flight_duration);

                SET i = i + 1;
            END WHILE;
        END IF;
        
        -- =================================================================
        -- ATRIBUIÇÃO DE MATRÍCULAS (ROUND-ROBIN PARA VOOS SEM REGISTRO)
        -- =================================================================
        
        SELECT GROUP_CONCAT(registration ORDER BY registration) INTO reg_list FROM frota WHERE model = v_model;
        
        IF reg_list IS NOT NULL THEN
            SET reg_count = LENGTH(reg_list) - LENGTH(REPLACE(reg_list, ',', '')) + 1;
            SET v_reg_index = 1;
            SET flight_done = FALSE;
            
            BEGIN
                DECLARE flight_cursor CURSOR FOR
                    SELECT primare_key FROM voos
                    WHERE flightPlan_aircraft_model = v_model AND registration IS NULL
                    ORDER BY createdAt ASC;
                    
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET flight_done = TRUE;
                
                OPEN flight_cursor;
                flight_loop: LOOP
                    FETCH flight_cursor INTO v_flight_pk;
                    IF flight_done THEN LEAVE flight_loop; END IF;
                    
                    SET v_reg_index = (v_reg_index - 1) % reg_count + 1;
                    
                    SET v_reg_str = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(reg_list, ','), ',', v_reg_index), ',', -1));
                    
                    UPDATE voos SET registration = v_reg_str WHERE primare_key = v_flight_pk;
                    
                    SET v_reg_index = v_reg_index + 1;
                END LOOP flight_loop;
                CLOSE flight_cursor;
            END;
        END IF;

    END LOOP;

    CLOSE aircraft_cursor;
END$$
DELIMITER;
```

### Estrutura do Projeto

```
.
├── admin/
├── assets/
├── config/
│   ├── lang/
│   └── settings.json
├── financial/
│   ├── index.php
│   └── relatorio_aeronave.php
├── home/
│   └── index.php
├── src/
│   ├── config_loader.php
│   └── disable_pilot.php
├── est.php
├── estatisticas_piloto.php
└── index.php
```
