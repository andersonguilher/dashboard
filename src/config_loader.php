<?php
// ===================================
// 0. CONFIGURAÇÃO DE FUSO HORÁRIO (ADICIONADO)
// Garante que todas as operações de data e hora sejam baseadas em UTC/GMT.
// ===================================
date_default_timezone_set('UTC');

// ===================================
// 1. CARREGAR CONFIGURAÇÕES GLOBAIS DO ARQUIVO JSON
// ===================================
$settings_file = __DIR__ . '/../config/settings.json';
$settings = [];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
}

// Define os valores padrão caso o arquivo esteja vazio ou corrompido
$lang_code = $settings['language'] ?? 'pt';
$theme = $settings['theme'] ?? 'default';

// ===================================
// 2. DEFINIR MAPEAMENTOS DO BANCO DE DADOS COMO CONSTANTES GLOBAIS
// ===================================
$db_mappings = $settings['database_mappings'] ?? [];

// Nomes padrão para garantir que a aplicação não quebre se a configuração estiver ausente
define('PILOTS_TABLE', $db_mappings['pilots_table'] ?? 'Dados_dos_Pilotos');
define('COL_ID_PILOTO', $db_mappings['columns']['id_piloto'] ?? 'id_piloto');
define('COL_POST_ID', $db_mappings['columns']['post_id'] ?? 'post_id');
define('COL_FIRST_NAME', $db_mappings['columns']['first_name'] ?? 'first_name');
define('COL_LAST_NAME', $db_mappings['columns']['last_name'] ?? 'last_name');
define('COL_VATSIM_ID', $db_mappings['columns']['vatsim_id'] ?? 'vatsim_id');
define('COL_IVAO_ID', $db_mappings['columns']['ivao_id'] ?? 'ivao_id');
define('COL_FOTO_PERFIL', $db_mappings['columns']['foto_perfil'] ?? 'foto_perfil');
define('COL_VALIDADO', $db_mappings['columns']['validado'] ?? 'validado');
define('COL_MATRICULA', $db_mappings['columns']['matricula'] ?? 'matricula');
define('COL_EMAIL_PILOTO', $db_mappings['columns']['email_piloto'] ?? 'email_piloto'); // NOVA CONSTANTE

// ===================================
// 3. CARREGAR IDIOMA
// ===================================
$lang_file = __DIR__ . '/../config/lang/' . $lang_code . '.php';

if (file_exists($lang_file)) {
    require_once $lang_file;
} else {
    // Fallback para português se o arquivo de idioma não for encontrado
    require_once __DIR__ . '/../config/lang/pt.php';
}

// Função de atalho para tradução
function t($key) {
    global $lang;
    return $lang[$key] ?? $key; // Retorna a chave se a tradução não for encontrada
}

// ===================================
// 4. APLICAR TEMA DE CORES GLOBAL
// ===================================
function apply_color_theme() {
    global $theme; // Usa a variável global $theme lida do arquivo JSON
    $css_vars = '';

    switch ($theme) {
        case 'dark':
            $css_vars = "
                --background-color: #1c1c1e; --card-background-color: #2c2c2e; --text-color: #f0f0f0;
                --text-color-light: #a0a0a0; --border-color: #444444; --primary-color: #0a84ff;
                --primary-color-dark: #339aff;
            ";
            break;
        case 'ocean':
            $css_vars = "
                --background-color: #e0f7fa; --card-background-color: #ffffff; --text-color: #004d40;
                --text-color-light: #00796b; --border-color: #b2dfdb; --primary-color: #00acc1;
                --primary-color-dark: #00838f;
            ";
            break;
        case 'red':
            $css_vars = "
                --background-color: #f8f0f1; --card-background-color: #ffffff; --text-color: #333333;
                --text-color-light: #721c24; --border-color: #f5c6cb; --primary-color: #dc3545;
                --primary-color-dark: #b02a37;
            ";
            break;
        default: // 'default'
             $css_vars = "
                --background-color: #f0f2f5; --card-background-color: #ffffff; --text-color: #333333;
                --text-color-light: #666666; --border-color: #e0e0e0; --primary-color: #0d6efd;
                --primary-color-dark: #0a58ca;
            ";
            break;
    }

    echo "<style>:root { " . $css_vars . " }</style>";
}