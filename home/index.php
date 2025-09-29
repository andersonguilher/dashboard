<?php
// =================================================================
// 0. CARREGAR CONFIGURAÇÕES DE IDIOMA E TEMA
// =================================================================
require_once __DIR__ . '/../src/config_loader.php';

// --- Carregamento de Configurações Dinâmicas ---
$settings_file = __DIR__ . '/../config/settings.json';
$settings = [];
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
}

// Definição de fallback para as novas configurações
$company_name = $settings['company_name'] ?? 'Kafly VA';
$homepage_config = $settings['homepage_config'] ?? [];

$logo_url = $homepage_config['logo_url'] ?? 'assets/logo.png';
// Removida a variável $hero_image_url
$menu_links_data = $homepage_config['menu_links'] ?? [
    [ "text" => "Home", "url" => "index.php", "is_cta" => false ],
    [ "text" => "Frota", "url" => "financial/index.php", "is_cta" => false ],
    [ "text" => "Pilotos", "url" => "est.php", "is_cta" => false ],
    [ "text" => "Estatísticas", "url" => "dashboard.php", "is_cta" => false ],
    [ "text" => "Inscreva-se", "url" => "#", "is_cta" => true ]
];

// --- CORREÇÃO DE ERRO: Inicialização do KPI Data ---
// Simulação de dados do Dashboard
$kpi_data_home = [
    'hours' => number_format(30252, 0, ',', '.'),
    'flights' => number_format(14810, 0, ',', '.'),
    'pilots' => number_format(85, 0, ',', '.'),
];
// ---------------------------------------------------

// NOVO: CONEXÃO COM BANCO DE DADOS E FUNÇÕES
require_once __DIR__ . '/../../../config_db.php'; // CORREÇÃO DO CAMINHO RELATIVO

$conn_voos = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);
$conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);

function format_seconds_to_hm($seconds)
{
  if (!$seconds || $seconds <= 0) return '00:00';
  $h = floor($seconds / 3600);
  $m = floor(($seconds % 3600) / 60);
  return sprintf('%02d:%02d', $h, $m);
}
function format_seconds_to_float($seconds)
{
  if (!$seconds || $seconds <= 0) return 0.00;
  return round($seconds / 3600, 2);
}
// FIM NOVO: FUNÇÕES

// Funções de tradução (baseadas na estrutura do seu projeto)
function t_home($key, $default) {
    global $lang, $company_name;
    // Para chaves de Hero, usa um fallback específico com o nome da companhia.
    $translations = [
        'hero_title' => "Voe Mais Alto. Voe {$company_name}.",
        'hero_subtitle' => 'Junte-se à nossa comunidade e explore os céus com realismo e paixão.',
        'cta_button' => 'Conheça nossos Pilotos',
        'footer_text' => "&copy; " . date('Y') . " {$company_name} Virtual Airlines. Todos os direitos reservados.",
        'kpi_total_hours' => 'Horas Totais',
        'kpi_total_flights' => 'Voos Totais',
        'kpi_total_pilots' => 'Pilotos Ativos',
        'gadget_title_recent' => 'Últimos Voos',
        'gadget_title_champion' => 'Destaque da Semana',
        'gadget_title_fleet' => 'Status da Frota',
        'gadget_title_route' => 'Rota Mais Ativa',
        'aircraft_model' => 'A320',
        'route_name' => 'SBGR ↔ SBGL',
        'flights_label' => 'Voos',
        'hours_label' => 'Horas',
        'hours_in_month' => 'Horas no Mês',
        'visitor' => 'Visitante',
    ];
    return $lang[$key] ?? $translations[$key] ?? $default;
}

// --- DADOS PARA HOVER CARD (Replicado de index.php) ---
$pilots_details = [];

// 1. Fetch core pilot details (names, photos, IDs) and total hours/flights
$pilots_sql = "
    SELECT 
        p." . COL_VATSIM_ID . " as vatsim_id, 
        p." . COL_IVAO_ID . " as ivao_id, 
        CONCAT(p." . COL_FIRST_NAME . ", ' ', p." . COL_LAST_NAME . ") as display_name, 
        p." . COL_FOTO_PERFIL . " as foto_perfil,
        COALESCE(SUM(v.time), 0) as total_seconds,
        COUNT(v.id) as total_flights
    FROM 
        " . DB_PILOTOS_NAME . ".`" . PILOTS_TABLE . "` p
    LEFT JOIN 
        " . DB_VOOS_NAME . ".voos v ON v.userId = p." . COL_VATSIM_ID . " OR v.userId = p." . COL_IVAO_ID . "
    WHERE
        p." . COL_VALIDADO . " = 'true'
    GROUP BY
        p." . COL_ID_PILOTO . ", p.post_id, p.first_name, p.last_name, p.foto_perfil, p.vatsim_id, p.ivao_id
";
$pilots_result = $conn_pilotos->query($pilots_sql);

if ($pilots_result) {
  while ($pilot = $pilots_result->fetch_assoc()) {
    $name = trim($pilot['display_name']);
    
    // CORREÇÃO: Pega o caminho RAW do banco de dados, confiando que ele esteja correto (ex: assets/images/joao.png)
    $photo = $pilot['foto_perfil']; 
    
    // NORMALIZAÇÃO PARA ROBUSTEZ: Se for um caminho relativo, remove barras iniciais para o JS prefixar corretamente.
    if (strpos($photo, 'http') === false && strpos($photo, '//') !== 0) {
        $photo = trim($photo, '/');
    }

    $vatsim_id = $pilot['vatsim_id'];
    $ivao_id = $pilot['ivao_id'];
    $total_seconds = $pilot['total_seconds'];
    $total_flights = $pilot['total_flights'];
    $first_name = explode(' ', $name)[0];

    // Adicionamos 'id' (para o link) e 'first_name' (para o display na lista)
    $details = [
        'name' => $name,
        'first_name' => $first_name,
        'photo' => $photo, // Foto agora tem o caminho RAW normalizado
        'total_hours' => floor($total_seconds / 3600),
        'total_flights' => $total_flights,
        'monthly_data' => [], 
        'id' => $vatsim_id ?: $ivao_id 
    ];

    if (!empty($vatsim_id)) { $pilots_details[$vatsim_id] = $details; }
    if (!empty($ivao_id)) { $pilots_details[$ivao_id] = $details; }
  }
}

// 2. Fetch current month's flights for mini-chart data
$current_month_flights_sql = "
    SELECT 
        userId, 
        DAY(createdAt) as day, 
        SUM(time) as total_seconds
    FROM 
        " . DB_VOOS_NAME . ".voos
    WHERE
        createdAt >= DATE_FORMAT(NOW(), '%Y-%m-01')
    GROUP BY 
        userId, DAY(createdAt)
    ORDER BY
        day ASC
";
$current_month_flights_result = $conn_voos->query($current_month_flights_sql);

if ($current_month_flights_result) {
    $daily_data_map = [];
    while ($flight = $current_month_flights_result->fetch_assoc()) {
        $userId = $flight['userId'];
        $day = $flight['day'];
        $total_seconds = $flight['total_seconds'];

        if (!isset($daily_data_map[$userId])) { $daily_data_map[$userId] = []; }
        $daily_data_map[$userId][$day] = $total_seconds;
    }

    $current_day_of_month = date('j');
    foreach ($pilots_details as $userId => &$details) {
        $monthly_data = array_fill(1, $current_day_of_month, 0); 
        if (isset($daily_data_map[$userId])) {
            $current_cumulative_seconds = 0;
            for ($day = 1; $day <= $current_day_of_month; $day++) {
                $current_cumulative_seconds += $daily_data_map[$userId][$day] ?? 0;
                $monthly_data[$day] = round($current_cumulative_seconds / 3600, 1);
            }
        }
        $details['monthly_data'] = array_values($monthly_data);
    }
}
unset($details); // Quebra a referência

// 3. BUSCAR DADOS REAIS DE VOOS (Updated)
$recent_flights_data = [];
if ($conn_voos) {
    $flights_sql = "
        SELECT 
            createdAt, 
            userId, 
            flightPlan_departureId, 
            flightPlan_arrivalId, 
            time, 
            network 
        FROM voos 
        ORDER BY createdAt DESC 
        LIMIT 5";

    $flights_result = $conn_voos->query($flights_sql);

    if ($flights_result) {
        while ($flight = $flights_result->fetch_assoc()) {
            $userId = $flight['userId'];
            $pilot_info = $pilots_details[$userId] ?? ['first_name' => t_home('visitor', 'Visitante'), 'id' => null];

            $flight_data = [
                'date' => (new DateTime($flight['createdAt']))->format('d/m'),
                'pic' => $pilot_info['first_name'], // Display only first name
                'route' => "{$flight['flightPlan_departureId']}-{$flight['flightPlan_arrivalId']}",
                'time' => format_seconds_to_hm($flight['time']),
                'network' => $flight['network'],
                'user_id' => $userId, 
                'pilot_details' => $pilots_details[$userId] ?? null 
            ];
            $recent_flights_data[] = $flight_data;
        }
    }
    $conn_voos->close();
}
if ($conn_pilotos) { $conn_pilotos->close(); }

// --- DADOS ADICIONAIS PARA GADGETS (SIMULADOS mantidos para os outros cards) ---
$champion_pilot_data = [
    'name' => 'MARCELO PIMENTA',
    'hours' => '33.10',
    'image' => 'assets/images/piloto.png',
];

$additional_gadgets_data = [
    'aircraft_online' => 8,
    'most_popular_route' => t_home('route_name', 'SBGR ↔ SBGL'),
    'total_fleet' => 45,
    'main_aircraft_model' => t_home('aircraft_model', 'A320'),
];

// PREPARAÇÃO DO PREFIXO DE CAMPEÃO (IDÊNTICO a index.php)
// Usamos t() que é definida no config_loader e faz parte do escopo global
$pilot_of_the_week_prefix = str_replace(' Cat. ', '', t('pilot_of_the_week')); 
// ---------------------------------------------------
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $company_name ?> | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <?php apply_color_theme(); // Aplica o tema de cores do dashboard ?>

    <style>
        /* ADDED GLOBAL BOX-SIZING AND OVERFLOW FIX */
        * {
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            min-height: 100vh; /* Para o Sticky Footer */
        }

        /* Variáveis de cores baseadas no TEMA ATIVO (Corrigido para linkar ao config_loader.php) */
        :root {
            /* Utiliza as cores dinâmicas definidas em config_loader.php */
            --color-primary: var(--primary-color, #dc3545); /* Vermelho Principal */
            --color-background-light: #fff;
            --color-text: var(--text-color, #333333);
            --color-dark: #333333; /* Charcoal/Neutral Dark (Usado apenas para texto e sombra) */
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-background);
            color: var(--color-text);
            
            /* STICKY FOOTER FLEXBOX */
            display: flex;
            flex-direction: column;
        }
        
        /* HEADER / NAV (CLEAN/LIGHT) */
        .header {
            background-color: var(--color-background-light); /* Fundo Branco */
            border-bottom: 1px solid var(--border-color, #e0e0e0); /* Borda sutil */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: fixed; /* MUDANÇA: Agora é fixo na viewport */
            top: 0;
            width: 100%; /* Garante que ocupe toda a largura */
            z-index: 1000;
        }

        .navbar {
            position: relative; /* Necessário para posicionar o logo */
            display: flex; /* Mudado para flex */
            justify-content: center; /* Centraliza o menu */
            align-items: center; /* Alinha tudo verticalmente */
            max-width: 1400px;
            margin: auto;
            padding: 15px 20px;
        }

        .logo {
            position: absolute; /* Tira o logo do fluxo para que o menu se centralize */
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;

            font-size: 1.5em; 
        }
        .logo img {
            height: 65px; /* FIXO: Altura do logo aumentada para 45px */
            width: auto;
            vertical-align: middle;
        }

        .nav-menu {
            display: inline-flex; /* Centrado pelo parent's text-align: center; */
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
            gap: 5px; /* Espaçamento sutil */
        }

        .nav-item a {
            color: var(--color-text); /* Texto Escuro */
            text-decoration: none;
            padding: 10px 18px; /* Mais espaçamento horizontal para visual moderno */
            display: block;
            font-size: 0.9em;
            font-weight: 400; /* Mais sutil */
            transition: color 0.3s, background-color 0.3s;
        }

        .nav-item a:hover {
            color: var(--color-primary); /* Destaque Primário (Vermelho) */
        }
        
        .nav-item a.active {
            color: var(--color-primary);
        }

        .cta-button {
            background-color: var(--color-primary);
            color: #fff !important;
            border-radius: 5px;
            padding: 8px 15px !important;
            margin-left: 15px;
            font-weight: 700;
            text-transform: uppercase;
            transition: background-color 0.3s;
        }
        
        .cta-button:hover {
            background-color: var(--primary-color-dark, #a02a37); /* Usa o tom escuro do tema */
            color: #fff !important;
        }

        /* MAIN SECTION FIX */
        main {
            flex-grow: 1; /* Garante que o main ocupe todo o espaço restante */
            padding-top: 95px; /* NOVO: Compensação pela altura do cabeçalho fixo */
        }

        /* HERO SECTION */
        .hero {
            position: relative;
            height: 40vh; /* REDUZIDO: Para 40vh */
            background-color: var(--color-background-light); /* Fundo Branco */
            
            background-size: contain; 
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: scroll;
            
            display: flex;
            align-items: center; /* Centering Vertical */
            justify-content: center; /* Centering Horizontal */
            text-align: center;
            color: var(--color-text); /* Texto principal do Hero agora é escuro */
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Ajustado o gradiente para usar o Charcoal de forma sutil */
            background: linear-gradient(
                rgba(51, 51, 51, 0.0), /* Topo transparente */
                rgba(51, 51, 51, 0.1) 30%,
                rgba(51, 51, 51, 0.3) 50%,
                rgba(51, 51, 51, 0.1) 70%,
                rgba(51, 51, 51, 0.0)  /* Base transparente */
            );
        }

        /* KPI BAR */
        .kpi-bar {
            position: absolute;
            top: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95); /* Fundo Branco Limpo */
            padding: 10px 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            border-bottom: 2px solid var(--color-primary); /* Borda primária (Vermelha) */
        }

        .kpi-content {
            display: flex;
            justify-content: space-around;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        .kpi-item {
            text-align: center;
            color: var(--color-text); /* Texto Escuro */
            padding: 0 20px;
        }

        .kpi-item .value {
            font-family: 'Oswald', sans-serif;
            font-size: 1.5em;
            font-weight: 700;
            color: var(--color-primary); /* Vermelho */
            margin-bottom: 2px;
        }

        .kpi-item .label {
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }
        /* END KPI BAR */

        .hero-content {
            position: relative;
            padding: 20px;
            max-width: 800px;
            z-index: 2;
            
            /* Centralização do conteúdo */
            display: flex;
            flex-direction: column;
            align-items: center; 
            
            /* Centralização baseada no Flexbox */
            margin-top: 0; 
        }

        .hero-title {
            font-family: 'Oswald', sans-serif;
            font-size: clamp(2.5rem, 6vw, 4rem);
            margin: 0 0 15px;
            line-height: 1.1;
            color: var(--color-text);
        }
        
        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.3rem);
            margin-bottom: 30px;
            font-weight: 300;
            color: var(--color-text);
        }
        
        .hero-cta {
            background-color: var(--color-primary);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            transition: background-color 0.3s, transform 0.2s;
            display: inline-block;
        }
        
        .hero-cta:hover {
            background-color: var(--primary-color-dark, #a02a37);
            transform: translateY(-2px);
        }

        /* GADGETS AREA STYLES */
        .gadget-area {
            max-width: 1400px;
            margin: -40px auto 30px auto; /* Aplicado margin negativo */
            padding: 0 20px;
        }

        .gadget-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr; /* Novo layout de 3 colunas */
            gap: 20px;
        }

        .gadget-card {
            background-color: var(--color-background-light);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 8px;
            padding: 15px; /* Reduzido padding interno */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .gadget-title {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--color-primary);
            border-bottom: 2px solid var(--border-color, #e0e0e0);
            padding-bottom: 8px; /* Reduzido padding */
            margin-bottom: 10px; /* Reduzido margin */
            display: flex; /* Para alinhar o ícone e o texto */
            align-items: center;
        }

        /* Recent Flights List */
        .flights-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .flights-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color, #e0e0e0);
            font-size: 0.95em;
            align-items: center; /* Alinha o ícone e texto verticalmente */
            cursor: pointer; /* Adiciona cursor de ponteiro para indicar interatividade */
        }
        .flights-list li:last-child {
            border-bottom: none;
        }
        .flight-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .flight-time {
            font-weight: 700;
            color: var(--color-primary);
        }
        .flight-route {
            font-weight: 500;
        }
        .network-icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            opacity: 0.8;
        }
        .pilot-pic {
            font-weight: 500;
        }


        /* Champion Card */
        .champion-details {
            text-align: center;
        }
        .champion-details img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--color-primary);
            margin-bottom: 10px;
        }
        .champion-details .name {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 5px;
        }
        .champion-details .hours {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--color-primary);
        }
        .champion-details .label {
            font-size: 0.8em;
            color: #555;
            text-transform: uppercase;
        }

        /* Generic Stat Card for new items */
        .stat-card-tech {
            text-align: center;
        }
        .stat-card-tech .main-value {
            font-family: 'Oswald', sans-serif;
            font-size: 2.2em;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 5px;
        }
        .stat-card-tech .sub-label {
            font-size: 0.9em;
            color: var(--color-text);
        }
        
        /* MAP SECTION STYLES */
        .map-section {
            padding: 30px 0;
            background-color: var(--background-color); /* Usa o fundo do dashboard/body */
        }
        
        .map-container {
            max-width: 1400px;
            margin: auto;
            padding: 0 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden; /* Garante que o iframe respeite o border-radius */
            background-color: var(--color-background-light);
        }

        /* FOOTER */
        .footer {
            background-color: var(--color-background-light); /* Fundo Branco Limpo */
            color: var(--color-text); /* Texto Escuro */
            text-align: center;
            padding: 20px 0;
            font-size: 0.85em;
            border-top: 1px solid var(--border-color, #e0e0e0); /* Borda cinza sutil */
        }
        
        /* HOVER CARD STYLES (Copiado e Adaptado de index.php) */
        #card-piloto-hover {
            display: none;
            position: fixed; /* Mudado para fixed para funcionar com o menu fixo */
            z-index: 2000; /* Z-index alto para ficar acima do menu */
            width: 250px;
            padding: 15px;
            background-color: var(--card-background-color, #fff);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 8px;
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(5px);
            opacity: 0; 
            transition: opacity 0.4s ease, transform 0.4s ease; /* Transição Suave */
            transform: translateY(5px); /* Início da Animação */
        }
        #card-piloto-hover.visible {
            opacity: 1;
            transform: translateY(0); /* Fim da Animação */
        }
        #card-piloto-hover .content {
          display: flex;
          flex-direction: column;
          align-items: center;
          text-align: center;
        }
        #card-piloto-hover .stat-info {
            display: flex;
            justify-content: space-around;
            width: 100%;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color, #e0e0e0);
        }
        #card-piloto-hover .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #card-piloto-hover .stat-label {
            font-size: 0.75em;
            color: var(--text-color-light, #666);
            text-transform: uppercase;
        }
        #card-piloto-hover .stat-value {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--color-primary);
        }
        #card-piloto-hover img {
          width: 80px;
          height: 80px;
          object-fit: cover;
          border-radius: 50%;
          margin-bottom: 10px;
          border: 2px solid var(--color-primary);
        }
        #card-piloto-hover .name {
          font-size: 1.1em;
          font-weight: 700;
          color: var(--color-text);
          margin-bottom: 5px;
        }
        .monthly-chart-title {
          font-size: 0.75em;
          color: var(--text-color-light, #666);
          text-transform: uppercase;
          margin-top: 10px;
          margin-bottom: 5px;
          text-align: center;
        }
        .monthly-chart-container {
            width: 100%;
            height: 60px;
            margin-top: 5px;
        }
        
        /* RESPONSIVENESS */
        @media (max-width: 1024px) {
            .gadget-grid {
                grid-template-columns: 1fr; /* Coluna única em telas menores */
            }
            .nav-menu {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 75px;
                left: 0;
                width: 100%;
                background-color: var(--color-background-light);
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
                z-index: 999;
                padding: 10px 0;
            }

            .nav-menu.open {
                display: flex;
            }

            .nav-item {
                width: 100%;
                text-align: center;
                border-bottom: 1px solid var(--border-color, #f0f0f0);
            }
            
            .nav-item:last-child {
                border-bottom: none;
            }

            .nav-item a {
                padding: 15px;
            }

            .cta-button {
                margin: 10px auto;
                display: block;
            }
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <?php if (strtolower(pathinfo($logo_url, PATHINFO_EXTENSION)) !== 'png'): ?>
                    <?= $company_name ?>
                <?php else: ?>
                    <img src="../<?= htmlspecialchars($logo_url) ?>" onerror="this.onerror=null; this.src='../assets/logo.png';" alt="<?= $company_name ?> Logo">
                <?php endif; ?>
            </a>
            
            <div class="menu-toggle" id="mobile-menu">
                <i class="fa-solid fa-bars"></i>
            </div>

            <ul class="nav-menu" id="nav-list">
                <?php 
                $has_cta = false;
                $cta_item = null;
                foreach ($menu_links_data as $item): 
                    
                    // Lógica para ajustar a URL e a classe ativa (Estamos em /home/index.php)
                    $link_url = htmlspecialchars($item['url']);
                    $final_url = $link_url;
                    $active_class = '';
                    
                    if (!$item['is_cta']) {
                        if ($link_url === 'home/index.php') {
                            // Link para si mesmo (Home)
                            $final_url = 'index.php';
                            $active_class = 'active';
                        } elseif ($link_url !== '#') {
                            // Todos os outros links internos precisam de '../' (e.g., Estatísticas, Frota, Pilotos)
                            $final_url = '../' . $link_url;
                        }
                    } else {
                         // Trata o link CTA (também precisa do prefixo se não for #)
                        if ($link_url !== '#') {
                            $final_url = '../' . $link_url;
                        }
                        $has_cta = true;
                        $cta_item = ['text' => $item['text'], 'url' => $final_url]; // Atualiza o CTA com a URL corrigida
                        continue;
                    }
                ?>
                    <li class="nav-item">
                        <a href="<?= $final_url ?>" class="<?= $active_class ?>">
                            <?= htmlspecialchars($item['text']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                
                <?php if ($has_cta && $cta_item): ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($cta_item['url']) ?>" class="cta-button">
                            <?= htmlspecialchars($cta_item['text']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero" style="background-image: url('<?= htmlspecialchars($hero_image_url) ?>');">
            <div class="hero-overlay"></div>
            
            <div class="kpi-bar">
                <div class="kpi-content">
                    <div class="kpi-item">
                        <div class="value"><?= $kpi_data_home['hours'] ?></div>
                        <div class="label"><?= t_home('kpi_total_hours', 'Horas Totais') ?></div>
                    </div>
                    <div class="kpi-item">
                        <div class="value"><?= $kpi_data_home['flights'] ?></div>
                        <div class="label"><?= t_home('kpi_total_flights', 'Voos Totais') ?></div>
                    </div>
                    <div class="kpi-item">
                        <div class="value"><?= $kpi_data_home['pilots'] ?></div>
                        <div class="label"><?= t_home('kpi_total_pilots', 'Pilotos Ativos') ?></div>
                    </div>
                </div>
            </div>

            <div class="hero-content">
                <h1 class="hero-title"><?= t_home('hero_title', "Voe Mais Alto. Voe {$company_name}.") ?></h1>
                <p class="hero-subtitle"><?= t_home('hero_subtitle', 'Junte-se à nossa comunidade e explore os céus com realismo e paixão.') ?></p>
                <a href="<?= htmlspecialchars($cta_item['url'] ?? '#') ?>" class="hero-cta"><?= t_home('cta_button', 'Conheça nossos Pilotos') ?></a>
            </div>
        </section>
        
        <section class="gadget-area">
            <div class="gadget-grid">
                
                <div class="gadget-card" style="grid-column: span 1;">
                    <h3 class="gadget-title"><i class="fa-solid fa-clock-rotate-left" style="margin-right: 8px;"></i><?= t_home('gadget_title_recent', 'Últimos Voos') ?></h3>
                    <ul class="flights-list">
                        <?php foreach ($recent_flights_data as $flight): 
                            $pilot_details_json = '';
                            if ($flight['pilot_details']) {
                                // Codifica os detalhes do piloto para o atributo data-pilot
                                $pilot_details_json = htmlspecialchars(json_encode($flight['pilot_details']), ENT_QUOTES, 'UTF-8');
                            }
                        ?>
                        <li data-pilot='<?= $pilot_details_json ?>'>
                            <span class="flight-info">
                                <?php if (isset($flight['network'])): ?>
                                    <img class="network-icon" src="../assets/<?= $flight['network'] === 'v' ? 'vatsim_logo.jpg' : 'ivao_logo.jpg' ?>" alt="<?= $flight['network'] === 'v' ? 'VATSIM' : 'IVAO' ?>">
                                <?php endif; ?>
                                <span style="opacity: 0.7;"><?= htmlspecialchars($flight['date']) ?></span>
                                <span class="pilot-pic"><?= htmlspecialchars($flight['pic']) ?></span>
                                <span class="flight-route"><?= htmlspecialchars($flight['route']) ?></span>
                            </span>
                            <span class="flight-time"><?= htmlspecialchars($flight['time']) ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($recent_flights_data)): ?>
                            <li><span class="flight-info" style="justify-content: center; width: 100%; color: var(--text-color-light);">Nenhum voo registrado recentemente.</span></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="gadget-card stat-card-tech" style="grid-column: span 1;">
                    <h3 class="gadget-title"><i class="fa-solid fa-helicopter" style="margin-right: 8px;"></i> <?= t_home('gadget_title_fleet', 'Status da Frota') ?></h3>
                    <div style="margin-bottom: 15px;">
                        <div class="main-value"><?= number_format($additional_gadgets_data['aircraft_online']) ?></div>
                        <div class="sub-label">Aeronaves Online</div>
                    </div>
                    <div style="border-top: 1px solid var(--border-color, #e0e0e0); padding-top: 10px;">
                        <div class="sub-label">Frota Total: <?= $additional_gadgets_data['total_fleet'] ?></div>
                        <div class="sub-label">Mais Comum: <?= t_home('aircraft_model', 'A320') ?></div>
                    </div>
                </div>

                <div class="gadget-card stat-card-tech" style="grid-column: span 1;">
                    <h3 class="gadget-title"><i class="fa-solid fa-route" style="margin-right: 8px;"></i> <?= t_home('gadget_title_route', 'Rota Mais Ativa') ?></h3>
                    <div style="margin-bottom: 15px;">
                        <div class="main-value" style="font-size: 1.8em;"><?= t_home('route_name', 'SBGR ↔ SBGL') ?></div>
                        <div class="sub-label">Popularidade Média</div>
                    </div>
                    <div style="border-top: 1px solid var(--border-color, #e0e0e0); padding-top: 10px;">
                        <div class="sub-label">
                            <i class="fa-solid fa-chart-line" style="color: var(--color-primary); margin-right: 5px;"></i> Alta Demanda Sazonal
                        </div>
                    </div>
                </div>
                
                <div class="gadget-card" style="grid-column: span 3;">
                     <h3 class="gadget-title"><i class="fa-solid fa-trophy" style="margin-right: 8px;"></i> <?= t_home('gadget_title_champion', 'Destaque da Semana') ?></h3>
                    <div class="champion-details" style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                        <img src="../assets/images/<?= htmlspecialchars($champion_pilot_data['image']) ?>" onerror="this.onerror=null; this.src='../assets/images/piloto.png';" alt="Foto Piloto">
                        <div>
                            <div class="name"><?= htmlspecialchars($champion_pilot_data['name']) ?></div>
                            <div class="label">Horas Voadas na Semana Anterior</div>
                            <div class="hours"><?= htmlspecialchars($champion_pilot_data['hours']) ?> h</div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
        
        <section class="map-section">
            <h3 class="gadget-title" style="margin: 0 auto 15px auto; max-width: 1400px; padding: 0 20px; border-bottom: 2px solid var(--border-color, #e0e0e0);"><i class="fa-solid fa-map-location-dot" style="margin-right: 8px;"></i>Mapa de Operações 3D</h3>
            <div class="map-container">
                <iframe src="https://kafly.com.br/mapa/3d/" frameborder="0" style="width: 100%; height: 600px; border: none; border-radius: 8px;" allowfullscreen></iframe>
            </div>
        </section>
        </main>

    <footer class="footer">
        <p><?= t_home('footer_text', "&copy; " . date('Y') . " {$company_name} Virtual Airlines. Todos os direitos reservados.") ?></p>
    </footer>

    <div id="card-piloto-hover">
        <div class="content">
            <img src="../assets/images/piloto.png" alt="Foto do Piloto">
            <div class="name"></div>
            <div id="champion-status-hover" style="font-size: 0.9em; font-weight: 700; margin-bottom: 10px; display: none;"></div>
            <div class="stat-info">
                <div class="stat-item">
                    <span class="stat-label"><?= t_home('flights_label', 'Voos') ?></span>
                    <span class="stat-value" id="card-vuelos">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?= t_home('hours_label', 'Horas') ?></span>
                    <span class="stat-value" id="card-horas">0</span>
                </div>
            </div>
            <div class="monthly-chart-title"><?= t_home('hours_in_month', 'Horas no Mês') ?></div>
            <div class="monthly-chart-container"><svg class="monthly-chart"></svg></div>
        </div>
    </div>
    <script>
        let tooltipTimeout;

        // Função para desenhar o mini gráfico de área
        function createMiniChart(data, element) {
          if (!data || data.length === 0) {
            element.innerHTML = '<span style="font-size: 0.8em; color: #999;">Sem dados neste mês.</span>';
            return;
          }

          const svgWidth = element.clientWidth;
          const svgHeight = element.clientHeight;
          // Garante que o maxVal não seja 0, para evitar divisão por zero
          const maxVal = Math.max(...data) > 0 ? Math.max(...data) : 1; 
          const points = data.map((val, i) => {
            const x = (i / (data.length - 1)) * svgWidth;
            // Inverte a coordenada Y (SVG 0,0 é topo-esquerda)
            const y = svgHeight - (val / maxVal) * svgHeight; 
            return `${x},${y}`;
          }).join(' ');

          const style = getComputedStyle(document.body);
          const primaryColor = style.getPropertyValue('--color-primary').trim() || '#dc3545';
          
          const svgContent = `
            <svg width="${svgWidth}" height="${svgHeight}">
              <polyline points="${points}" fill="none" stroke="${primaryColor}" stroke-width="2" />
              <path d="M0,${svgHeight} L${points} L${svgWidth},${svgHeight} Z" fill="${primaryColor}" opacity="0.2"/>
            </svg>
          `;
          element.innerHTML = svgContent;
        }

        // Função para exibir o cartão de detalhes do piloto
        function showPilotCard(e, element) {
            clearTimeout(tooltipTimeout);

            const pilotData = element.dataset.pilot;
            
            // 1. **REGRA DO VISITANTE**: Se não houver dados de piloto, esconde e sai.
            if (!pilotData || !pilotData.trim()) { 
                const card = document.getElementById('card-piloto-hover');
                card.classList.remove('visible'); 
                card.style.display = 'none';
                return;
            }

            const pilot = JSON.parse(pilotData);
            const card = document.getElementById('card-piloto-hover');
            const img = card.querySelector('img');
            const nameDiv = card.querySelector('.name');
            const cardVuelos = document.getElementById('card-vuelos');
            const cardHoras = document.getElementById('card-horas');
            const championStatusDiv = card.querySelector('#champion-status-hover');

            // 2. Atualiza dados
            // Trata o caminho da foto (Adiciona ../ se for caminho relativo)
            let photoPath = pilot.photo;
            if (photoPath && !photoPath.startsWith('http') && !photoPath.startsWith('//') && !photoPath.startsWith('/')) {
                 photoPath = `../${photoPath}`;
            } else if (!photoPath || photoPath === '') {
                 photoPath = '../assets/images/piloto.png';
            }
            
            img.src = photoPath;
            img.onerror = function() { this.src = '../assets/images/piloto.png'; };
            
            nameDiv.textContent = pilot.name;
            cardVuelos.textContent = pilot.total_flights;
            cardHoras.textContent = pilot.total_hours;
            
            // --- LÓGICA DO PILOTO DA SEMANA ---
            if (pilot.champion_category) {
                const category = pilot.champion_category;
                const prefix = "<?= $pilot_of_the_week_prefix ?>"; 
                const star_icon = '<i class="fa-solid fa-star" style="color: gold; margin-right: 5px;"></i>';
                const primaryColor = getComputedStyle(document.body).getPropertyValue('--color-primary').trim() || '#dc3545';
                const category_display = `<span style="background-color: ${primaryColor}; color: #fff; padding: 2px 5px; border-radius: 4px; margin-left: 5px;">${category}</span>`;
                championStatusDiv.innerHTML = `${star_icon} ${prefix} ${category_display}`;
                championStatusDiv.style.display = 'block';
            } else {
                championStatusDiv.style.display = 'none';
            }
            
            // 3. Cria o mini gráfico
            createMiniChart(pilot.monthly_data, card.querySelector('.monthly-chart-container'));

            // 4. Posiciona e exibe (Fixando a posição e iniciando a transição suave)
            card.style.left = `${e.clientX + 15}px`;
            
            card.style.display = 'block'; // Necessário para medir altura
            const cardHeight = card.offsetHeight || 250; 
            
            // Calcula a posição Y, ajustando para ficar no topo da linha e adiciona um pequeno offset
            let newY = e.clientY - (cardHeight / 2);
            
            // Corrige se sair da tela (topo/base)
            const windowHeight = window.innerHeight;
            if (newY < 10) newY = 10; 
            if (newY + cardHeight + 10 > windowHeight) newY = windowHeight - cardHeight - 10;
            
            card.style.top = `${newY}px`;
            
            // Adiciona a classe para iniciar a transição suave
            setTimeout(() => {
                card.classList.add('visible');
            }, 10);
        }

        // Função para esconder o cartão de detalhes do piloto
        function hidePilotCard() {
            // Atraso de 300ms para Hover Intent
            tooltipTimeout = setTimeout(() => {
                const card = document.getElementById('card-piloto-hover');
                card.classList.remove('visible'); // Inicia a transição suave de SAÍDA
                
                // Esconde o card com display: none APÓS o término da transição (400ms)
                setTimeout(() => {
                    card.style.display = 'none';
                }, 400); 
            }, 300); // 300ms de delay para evitar flicker
        }

        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('mobile-menu');
            const navList = document.getElementById('nav-list');
            const hoverCard = document.getElementById('card-piloto-hover');
            
            // Adiciona listeners para os itens da lista de voos (Fix para Flickering)
            document.querySelectorAll('.flights-list li').forEach(item => {
                // onmouseenter: Mostra o card (e limpa o timer de esconder)
                item.addEventListener('mouseenter', (e) => showPilotCard(e, item));
                
                // onmouseleave: Inicia o timer de esconder (300ms)
                item.addEventListener('mouseleave', hidePilotCard);
                
                // mousemove: Atualiza a posição do card para que ele siga o cursor na linha
                item.addEventListener('mousemove', (e) => {
                    if (item.dataset.pilot && item.dataset.pilot.trim()) {
                        // Se for um piloto, mantém o card visível e atualiza a posição
                        clearTimeout(tooltipTimeout);
                        
                        const card = document.getElementById('card-piloto-hover');
                        const cardHeight = card.offsetHeight || 250; 
                        let newY = e.clientY - (cardHeight / 2);
                        if (newY < 10) newY = 10; 
                        const windowHeight = window.innerHeight;
                        if (newY + cardHeight + 10 > windowHeight) newY = windowHeight - cardHeight - 10;
                        
                        card.style.top = `${newY}px`;
                        card.style.left = `${e.clientX + 15}px`;
                    }
                });
            });


            menuToggle.addEventListener('click', () => {
                navList.classList.toggle('open');
                const isOpened = navList.classList.contains('open');
                menuToggle.querySelector('i').classList.toggle('fa-bars', !isOpened);
                menuToggle.querySelector('i').classList.toggle('fa-xmark', isOpened);
            });
            
            // Listeners para evitar que o card suma ao passar o mouse sobre ele
            hoverCard.addEventListener('mouseenter', () => clearTimeout(tooltipTimeout));
            hoverCard.addEventListener('mouseleave', hidePilotCard);

        });
    </script>
</body>
</html>