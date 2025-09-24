# Dashboard de Operações de Voo

<img width="1622" height="930" alt="image" src="https://github.com/user-attachments/assets/834dd1db-2191-48d1-96bb-478efe30a60e" />


Um painel de controle completo e dinâmico, desenvolvido em PHP e JavaScript, para monitorar e analisar as operações de uma companhia aérea virtual (VA) ou comunidade de simulação de voo. O sistema oferece dashboards detalhados, estatísticas de pilotos, relatórios financeiros e um painel administrativo para uma configuração flexível e intuitiva.

---

## ✨ Funcionalidades Principais

O projeto é dividido em módulos principais, cada um com funcionalidades específicas:

### 📊 Dashboard Operacional (`index.php`)
- **Visão Geral:** KPIs (Indicadores Chave de Desempenho) com o total de horas voadas e número de voos da companhia.
- **Voos Recentes:** Tabela atualizada em tempo real com os últimos 10 voos realizados, mostrando rota, equipamento, piloto e rede (IVAO/VATSIM).
- **Piloto da Semana:** Destaque para os pilotos com mais horas em cada categoria de aeronave (Leve, Médio, Pesado).
- **Gráficos Interativos:**
  - **Horas Acumuladas no Mês:** Comparativo do total de horas voadas no mês atual contra o mês anterior.
  - **Horas de Voo (Últimos 7 Dias):** Análise diária das horas voadas na semana atual em comparação com a semana anterior.
  - **Voos Diários:** Contagem de voos por dia, comparando o mês atual com o anterior.
  - **Top 5 Pilotos:** Ranking dos pilotos com mais horas de voo na história da companhia.
- **Card de Piloto (Hover):** Passe o mouse sobre um voo para ver um resumo das estatísticas do piloto.

### 💰 Dashboard Financeiro (`/financial`)
- **Análise de Performance:** Visão completa da saúde financeira da companhia, com KPIs de receita e lucro mensal.
- **Comparativos:** Análise de variação percentual de receita, lucro e passageiros em relação ao mês anterior.
- **Gráfico de Evolução Anual:** Acompanhe a evolução de receitas, lucros e diferentes categorias de custos (combustível, manutenção, operações, taxas) ao longo do ano.
- **Performance por Aeronave:**
  - Ranking das 5 aeronaves mais lucrativas.
  - Tabela com a frota operacional, permitindo pesquisa e acesso a relatórios individuais.
- **Relatório por Modelo (`relatorio_aeronave.php`):** Página dedicada com uma análise financeira detalhada para um modelo de aeronave específico.

### 🧑‍✈️ Estatísticas de Pilotos (`est.php` e `estatisticas_piloto.php`)
- **Status Geral:** Painel para visualizar o status de todos os pilotos, com a possibilidade de filtrar por pilotos em alerta de inatividade.
- **Perfil Individual:** Página de estatísticas completa para cada piloto, contendo:
  - Resumo de horas totais, voos e tempo médio.
  - Filtro por rede (Geral, IVAO, VATSIM).
  - Ranking de aeroportos mais utilizados (origens e destinos).
  - Gráfico de horas acumuladas no mês.
  - Histórico dos últimos voos realizados.

### ⚙️ Painel de Administração (`/admin`)
- **Configuração Global:** Interface para personalizar o sistema sem precisar editar o código.
  - **Aparência:** Altere o tema de cores (Padrão, Escuro, Oceano, Vermelho).
  - **Idioma:** Defina o idioma padrão (Português ou Espanhol).
  - **Mapeamento de Banco de Dados:** Configure os nomes da tabela de pilotos e suas respectivas colunas, garantindo compatibilidade com diferentes estruturas de banco de dados.

---

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP 8+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Banco de Dados:** MySQL / MariaDB
- **Gráficos:** [Chart.js](https://www.chartjs.org/)
- **Ícones:** [Font Awesome](https://fontawesome.com/)

---

## 🚀 Instalação e Configuração

Siga os passos abaixo para instalar e executar o projeto em seu ambiente local ou servidor web.

### Pré-requisitos
- Servidor web (Apache, Nginx, etc.)
- PHP 8.0 ou superior com a extensão `mysqli` habilitada.
- Servidor de Banco de Dados MySQL ou MariaDB.

### 1. Clone o Repositório
```bash
git clone <URL_DO_SEU_REPOSITORIO>
cd <NOME_DA_PASTA_DO_PROJETO>
```

### 2. Configure o Banco de Dados
O sistema requer **dois bancos de dados separados**: um para os dados dos pilotos e outro para o registro de voos e da frota.

**a. Crie os Bancos de Dados:**
Use nomes de sua preferência. Por exemplo, `sua_va_pilotos` e `sua_va_voos`.
```sql
CREATE DATABASE `sua_va_pilotos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `sua_va_voos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**b. Crie as Tabelas:**
Execute as queries abaixo no banco de dados correspondente.

-   **No banco de dados de PILOTOS (`sua_va_pilotos`):**
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

-   **No banco de dados de VOOS (`sua_va_voos`):**
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

### 3. Configure a Conexão
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

### 4. Configure as Permissões
O painel administrativo (`/admin`) precisa de permissão de escrita para o arquivo `config/settings.json` para salvar as configurações de tema, idioma e mapeamento das colunas.
```bash
# Exemplo em um servidor Linux (execute na pasta raiz do projeto)
chmod 664 config/settings.json
chown www-data:www-data config/settings.json
```

### 5. Acesse o Painel
Após concluir os passos, acesse o projeto pelo seu navegador. Para configurar o sistema, navegue até a pasta `/admin`.

---

## 🤖 Utilitário de Banco de Dados (Opcional)

Para facilitar o preenchimento da tabela `frota`, o projeto conta com um *Stored Procedure* que pode ser executado para popular a frota automaticamente com base nos voos já registrados.

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

---

## 📁 Estrutura do Projeto

```
.
├── admin/               # Painel de configuração global
├── assets/              # Imagens, logos e outros recursos estáticos
├── config/              # Arquivos de configuração e idiomas
│   ├── lang/
│   │   ├── pt.php       # Arquivo de tradução (Português)
│   │   └── es.php       # Arquivo de tradução (Espanhol)
│   └── settings.json    # Configurações de tema, idioma e mapeamento de DB
├── financial/           # Módulo do Dashboard Financeiro
│   ├── index.php
│   └── relatorio_aeronave.php
├── src/                 # Lógica principal e carregadores
│   └── config_loader.php
├── est.php              # Página de status geral dos pilotos
├── estatisticas_piloto.php # Página de estatísticas individuais
└── index.php            # Página principal do Dashboard Operacional
```
````
