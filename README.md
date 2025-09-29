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
  -- Lógica detalhada para inserção de frota e atribuição de matrículas
END$$
DELIMITER ;
CALL sp_verificar_e_inserir_frota();
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
