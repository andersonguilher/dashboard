<?php
// =================================================================
// 0. CARREGAR CONFIGURAÇÕES DE IDIOMA E TEMA
// =================================================================
require_once __DIR__ . '/src/config_loader.php';

// =================================================================
// 1. CONFIGURAÇÃO SEGURA E CONEXÃO COM O BANCO DE DADOS
// =================================================================
require_once __DIR__ . '/../../config_db.php'; // Mantenha este se config_db.php está dois níveis acima de /public

// --- Conexões com os bancos de dados ---
$conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);
$conn_voos = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);

// --- Funções Auxiliares ---
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

// --- Precarga de dados dos pilotos para eficiencia ---
$pilots_map = [];
$pilots_photos = [];
$pilots_details = []; // Novo array para armazenar todos os detalhes

$pilots_sql = "
    SELECT 
        p." . COL_VATSIM_ID . ", 
        p." . COL_IVAO_ID . ", 
        CONCAT(p." . COL_FIRST_NAME . ", ' ', p." . COL_LAST_NAME . ") as display_name, 
        p." . COL_FOTO_PERFIL . ",
        COALESCE(SUM(v.time), 0) as total_seconds
    FROM 
        " . DB_PILOTOS_NAME . "." . PILOTS_TABLE . " p
    LEFT JOIN 
        " . DB_VOOS_NAME . ".voos v ON v.userId = p." . COL_VATSIM_ID . " OR v.userId = p." . COL_IVAO_ID . "
    WHERE
        p." . COL_VALIDADO . " = 'true'
    GROUP BY
        p." . COL_ID_PILOTO . "
    ORDER BY
        display_name ASC
";
$pilots_result = $conn_pilotos->query($pilots_sql);

if ($pilots_result) {
  while ($pilot = $pilots_result->fetch_assoc()) {
    $name = trim($pilot['display_name']);
    $photo = $pilot['foto_perfil'];
    $vatsim_id = $pilot['vatsim_id'];
    $ivao_id = $pilot['ivao_id'];
    $total_seconds = $pilot['total_seconds'];
    $total_flights = 0; // Contaremos no próximo passo

    $details = [
        'name' => $name,
        'photo' => $photo,
        'total_hours' => floor($total_seconds / 3600),
        'total_flights' => 0, // Será preenchido
        'monthly_data' => [] // Novo campo para o mini gráfico
    ];

    if (!empty($vatsim_id)) {
      $pilots_details[$vatsim_id] = array_merge($details, ['id' => $vatsim_id]);
      $pilots_map[$vatsim_id] = $name;
      $pilots_photos[$vatsim_id] = $photo;
    }
    if (!empty($ivao_id)) {
      $pilots_details[$ivao_id] = array_merge($details, ['id' => $ivao_id]);
      $pilots_map[$ivao_id] = $name;
      $pilots_photos[$ivao_id] = $photo;
    }
  }
}

// Lógica para obter os voos do mês atual para cada piloto
$current_month_flights_sql = "
    SELECT 
        userId, 
        DAY(createdAt) as day, 
        SUM(time) as total_seconds,
        COUNT(id) as total_flights
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
        $total_flights = $flight['total_flights'];

        if (!isset($daily_data_map[$userId])) {
            $daily_data_map[$userId] = [];
        }
        $daily_data_map[$userId][$day] = $total_seconds;
        
        // Atualiza o total de voos no array principal
        if (isset($pilots_details[$userId])) {
            $pilots_details[$userId]['total_flights'] += $total_flights;
        }
    }

    foreach ($pilots_details as $userId => &$details) {
        $monthly_data = array_fill(1, date('j'), 0);
        if (isset($daily_data_map[$userId])) {
            $current_cumulative_seconds = 0;
            foreach ($monthly_data as $day => $value) {
                $current_cumulative_seconds += $daily_data_map[$userId][$day] ?? 0;
                $monthly_data[$day] = round($current_cumulative_seconds / 3600, 1);
            }
        }
        $details['monthly_data'] = array_values($monthly_data);
    }
}

// --- Definição de Limites de Pouso por Categoria (fpm) ---
$landing_thresholds = [
    // [Green_Max, Yellow_Max] -> Red > Yellow_Max
    'L' => ['Green_Max' => 150, 'Yellow_Max' => 300], // Leve
    'M' => ['Green_Max' => 200, 'Yellow_Max' => 400], // Médio
    'H' => ['Green_Max' => 300, 'Yellow_Max' => 500], // Pesado
];

// --- Carregar Mapeamento de Categoria da Frota ---
$aircraft_categories = [];
$cat_result = $conn_voos->query("SELECT model, category FROM frota");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $aircraft_categories[$row['model']] = $row['category'];
    }
}

// --- O RESTANTE DA LÓGICA DE BUSCA DE DADOS ---
$recent_flights_result = $conn_voos->query("SELECT userId, callsign, flightPlan_aircraft_model as EQP, flightPlan_departureId as ORIG, flightPlan_arrivalId as DEST, time, createdAt, network FROM ".DB_VOOS_NAME.".voos ORDER BY createdAt DESC LIMIT 10");
$kpi_data = $conn_voos->query("SELECT SUM(time) as total_seconds, COUNT(*) as total_flights FROM ".DB_VOOS_NAME.".voos")->fetch_assoc();
$kpi_total_hours = number_format(floor($kpi_data['total_seconds'] / 3600));
$kpi_total_flights = number_format($kpi_data['total_flights']);
$weekly_champions = [];
foreach (['L', 'M', 'H'] as $category) {
  $sql = "SELECT userId, SUM(time) as total_time FROM ".DB_VOOS_NAME.".voos WHERE wakeTurbulence = '{$category}' AND createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY userId ORDER BY total_time DESC LIMIT 1";
  $result = $conn_voos->query($sql);
  $weekly_champions[$category] = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}
$top_weekly_result = $conn_voos->query("SELECT userId, SUM(time) as total_seconds FROM ".DB_VOOS_NAME.".voos WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY userId ORDER BY total_seconds DESC LIMIT 3");

// Lógica para o TOP 3 Landing Rate da Semana (1 por Categoria)
$top_landing_rate_result_by_cat = [];
$categories_to_check = ['L', 'M', 'H'];
$db_name = DB_VOOS_NAME;

$query_template = "
    SELECT 
        t1.userId, 
        MAX(t1.landing_vs) as best_landing_vs_signed, /* CORREÇÃO AQUI: MAX para pegar o valor negativo mais próximo de zero */
        ? as category_code,
        (
            SELECT t2.flightPlan_aircraft_model
            FROM {$db_name}.voos t2
            WHERE t2.userId = t1.userId
            AND t2.createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND t2.wakeTurbulence = ?
            GROUP BY t2.flightPlan_aircraft_model
            ORDER BY COUNT(*) DESC, MAX(t2.createdAt) DESC
            LIMIT 1
        ) AS main_aircraft_model
    FROM 
        {$db_name}.voos t1 
    WHERE 
        t1.createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        AND t1.landing_vs IS NOT NULL 
        AND t1.landing_vs < 0 
        AND t1.wakeTurbulence = ?
    GROUP BY 
        t1.userId 
    HAVING
        COUNT(*) >= 1 
    ORDER BY 
        best_landing_vs_signed DESC /* Usamos MAX para selecionar o melhor pouso */
    LIMIT 1
";

$stmt_landing = $conn_voos->prepare($query_template);

foreach ($categories_to_check as $cat) {
    if ($stmt_landing) {
        $stmt_landing->bind_param("sss", $cat, $cat, $cat);
        $stmt_landing->execute();
        $result = $stmt_landing->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $top_landing_rate_result_by_cat[] = $row;
        }
    }
}
$stmt_landing->close();

function get_daily_stats_for_month($conn, $interval_months, $aggregate_expression)
{
  if ($interval_months == 0) {
    $start_date_sql = "DATE_FORMAT(NOW(), '%Y-%m-01')";
    $end_date_sql = "DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')";
  } else {
    $start_date_sql = "DATE_FORMAT(NOW() - INTERVAL {$interval_months} MONTH, '%Y-%m-01')";
    $end_date_sql = "DATE_FORMAT(NOW() - INTERVAL " . ($interval_months - 1) . " MONTH, '%Y-%m-01')";
  }
  $sql = "SELECT DAY(createdAt) as day, {$aggregate_expression} as value FROM ".DB_VOOS_NAME.".voos WHERE createdAt >= {$start_date_sql} AND createdAt < {$end_date_sql} GROUP BY day ORDER BY day ASC";
  $result = $conn->query($sql);
  $daily_data = array_fill(1, 31, 0);
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $daily_data[$row['day']] = $row['value'];
    }
  }
  return $daily_data;
}
$daily_seconds_actual = get_daily_stats_for_month($conn_voos, 0, 'SUM(time)');
$daily_seconds_anterior = get_daily_stats_for_month($conn_voos, 1, 'SUM(time)');
$chart_data_horas_mes_actual = [];
$running_total_actual = 0;
$current_day_of_month = date('j');
foreach ($daily_seconds_actual as $day => $seconds) {
    if ($day > $current_day_of_month) {
        break;
    }
    $running_total_actual += $seconds;
    $chart_data_horas_mes_actual[] = round($running_total_actual / 3600, 1);
}
$chart_data_horas_mes_anterior = [];
$running_total_anterior = 0;
foreach ($daily_seconds_anterior as $seconds) {
  $running_total_anterior += $seconds;
  $chart_data_horas_mes_anterior[] = round($running_total_anterior / 3600, 1);
}
$chart_labels_dias_mes = range(1, 31);

// --- LÓGICA REVISADA PARA EXIBIR A SEMANA CORRENTE (Dom-Sáb) ---

// 1. Definir os dias da semana para o eixo X do gráfico (AGORA DINÂMICO)
$chart_labels_dias_semana = t('days_of_week_abbr');
$chart_data_horas_semana_atual_corrigido = array_fill(0, 7, 0);
$chart_data_horas_semana_anterior_corrigido = array_fill(0, 7, 0);

// 2. Calcular as datas de início da semana atual e anterior (considerando Domingo como início)
$today_day_of_week = date('w'); // Retorna 0 para Domingo, 1 para Segunda..., 6 para Sábado
$start_of_current_week = date('Y-m-d', strtotime("-$today_day_of_week days"));
$start_of_previous_week = date('Y-m-d', strtotime("$start_of_current_week -7 days"));

// 3. Buscar dados da semana ATUAL (Do último domingo até hoje)
$sql_semana_atual_corrigido = "
    SELECT DATE(createdAt) as dia, SUM(time) as total_seconds
    FROM " . DB_VOOS_NAME . ".voos
    WHERE createdAt >= '{$start_of_current_week} 00:00:00'
    GROUP BY dia";

$result_semana_atual_corrigido = $conn_voos->query($sql_semana_atual_corrigido);
$map_semana_atual_corrigido = [];
if ($result_semana_atual_corrigido) {
    while ($row = $result_semana_atual_corrigido->fetch_assoc()) {
        $map_semana_atual_corrigido[$row['dia']] = round($row['total_seconds'] / 3600, 1);
    }
}

// 4. Buscar dados da semana ANTERIOR
$sql_semana_anterior_corrigido = "
    SELECT DATE(createdAt) as dia, SUM(time) as total_seconds
    FROM " . DB_VOOS_NAME . ".voos
    WHERE createdAt >= '{$start_of_previous_week} 00:00:00' AND createdAt < '{$start_of_current_week} 00:00:00'
    GROUP BY dia";

$result_semana_anterior_corrigido = $conn_voos->query($sql_semana_anterior_corrigido);
$map_semana_anterior_corrigido = [];
if ($result_semana_anterior_corrigido) {
    while ($row = $result_semana_anterior_corrigido->fetch_assoc()) {
        $map_semana_anterior_corrigido[$row['dia']] = round($row['total_seconds'] / 3600, 1);
    }
}

// 5. Montar os arrays finais para o gráfico
for ($i = 0; $i < 7; $i++) {
    $data_da_semana_atual = date('Y-m-d', strtotime("$start_of_current_week +$i days"));
    $data_da_semana_anterior = date('Y-m-d', strtotime("$start_of_previous_week +$i days"));

    // Preenche o array da semana anterior
    $chart_data_horas_semana_anterior_corrigido[$i] = $map_semana_anterior_corrigido[$data_da_semana_anterior] ?? 0;
    
    // Preenche o array da semana atual, mas define como nulo para dias futuros, para que a linha não avance no tempo
    if (strtotime($data_da_semana_atual) > time()) {
        $chart_data_horas_semana_atual_corrigido[$i] = null;
    } else {
        $chart_data_horas_semana_atual_corrigido[$i] = $map_semana_atual_corrigido[$data_da_semana_atual] ?? 0;
    }
}

$chart_data_vuelos_mes_actual = array_values(get_daily_stats_for_month($conn_voos, 0, 'COUNT(*)'));
$chart_data_vuelos_mes_anterior = array_values(get_daily_stats_for_month($conn_voos, 1, 'COUNT(*)'));
$chart_labels_top_pilots = [];
$chart_data_top_pilots_hours = [];
$top_pilots_sql = "
  SELECT 
    v.userId, 
    SUM(v.time) as total_seconds 
  FROM 
    ".DB_VOOS_NAME.".voos v
  JOIN 
    ".DB_PILOTOS_NAME."." . PILOTS_TABLE . " p ON v.userId = p." . COL_VATSIM_ID . " OR v.userId = p." . COL_IVAO_ID . "
  WHERE 
    p." . COL_VALIDADO . " = 'true'
  GROUP BY 
    v.userId 
  ORDER BY 
    total_seconds DESC
  LIMIT 5
";
$top_pilots_result = $conn_voos->query($top_pilots_sql);
if ($top_pilots_result) {
  while ($row = $top_pilots_result->fetch_assoc()) {
    $chart_labels_top_pilots[] = $pilots_map[$row['userId']] ?? 'Desconhecido';
    $chart_data_top_pilots_hours[] = floor($row['total_seconds'] / 3600);
  }
}
$conn_pilotos->close();
$conn_voos->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('dashboard_title') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* O :root FOI REMOVIDO DAQUI */
    body {
      font-family: 'Roboto', sans-serif;
      background-color: var(--background-color);
      color: var(--text-color);
      margin: 0;
    }
    .container {
      max-width: 1600px;
      margin: 0 auto;
      padding: 10px;
    }
    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 15px;
    }
    .card {
      background-color: var(--card-background-color);
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      padding: 15px;
      display: flex;
      flex-direction: column;
    }
    .card-title {
      margin: 0 0 15px 0;
      font-size: 0.9em;
      font-weight: 500;
      color: var(--text-color-light);
      text-transform: uppercase;
      text-align: center; 
    }
    .card-content {
      flex-grow: 1;
      position: relative;
    }
    .vuelos-realizados .card-content {
      max-height: 300px;
      overflow-y: auto;
    }
    .vuelos-realizados table {
      width: 100%;
    }
    .vuelos-realizados thead {
      position: sticky;
      top: 0;
      background-color: var(--card-background-color);
      z-index: 10;
    }    
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85em;
    }
    th,
    td {
      text-align: left;
      padding: 8px 4px;
      border-bottom: 1px solid var(--border-color);
      vertical-align: middle;
    }
    th {
      font-weight: 500;
      color: var(--text-color-light);
    }
    tbody tr:nth-child(even) {
      background-color: #f8f9fa;
    }
    
    tbody tr:hover {
      background-color: #e9ecef !important;
      transition: background-color 0.3s ease;
      cursor: pointer;
    }

    .network-logo {
        height: 16px;
        display: block;
        margin: auto;
    }
    table th:first-child,
    table td:first-child {
        width: 20px;
        padding: 8px 5px;
    }
    .pic-visitante {
        color: var(--text-color-light);
        font-style: italic;
        font-size: 0.9em;
    }
    .kpi-value {
      font-size: 2.5em;
      font-weight: 700;
      color: var(--primary-color-dark);
      text-align: center;
      margin: auto;
    }
    .champion-pilot {
      text-align: center;
      margin: auto;
    }
    .champion-pilot img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 8px;
      border: 2px solid var(--primary-color);
    }
    .champion-pilot .name {
      font-weight: 500;
      font-size: 0.9em;
    }
    .champion-pilot .hours {
      color: var(--primary-color);
      font-weight: 700;
      font-size: 1.2em;
    }
    .top-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .top-list li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px 0;
      border-bottom: 1px solid var(--border-color);
    }
    .top-list li:last-child {
      border-bottom: none;
    }
    .top-list .pilot-details {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .top-list .pilot-photo {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      object-fit: cover;
    }
    .top-list .name {
      font-weight: 500;
      font-size: 0.9em;
    }
    .top-list .hours {
      font-weight: 700;
    }
    .chart-container {
      min-height: 180px;
      width: 100%;
    }
    .horas-totales .card-content {
      max-height: 280px;
      overflow-y: auto;
    }
    .pilot-link {
        color: var(--text-color);
        text-decoration: none;
        font-weight: 500;
    }
    .pilot-link:hover {
        color: var(--primary-color);
        text-decoration: underline;
    }

    #card-piloto-hover {
      display: none;
      position: absolute;
      z-index: 1000;
      width: 250px;
      padding: 15px;
      background-color: var(--card-background-color);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
      backdrop-filter: blur(5px);
      opacity: 0; 
      transition: opacity 0.3s ease; 
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
        border-top: 1px solid var(--border-color);
    }
    #card-piloto-hover .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    #card-piloto-hover .stat-label {
        font-size: 0.75em;
        color: var(--text-color-light);
        text-transform: uppercase;
    }
    #card-piloto-hover .stat-value {
        font-size: 1.1em;
        font-weight: 700;
        color: var(--primary-color);
    }
    #card-piloto-hover img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 50%;
      margin-bottom: 10px;
      border: 2px solid var(--primary-color);
    }
    #card-piloto-hover .name {
      font-size: 1.1em;
      font-weight: 700;
      color: var(--primary-color-dark);
      margin-bottom: 5px;
    }
    .monthly-chart {
      width: 100%;
      height: 60px;
      margin-top: 15px;
    }
    .monthly-chart-title {
      font-size: 0.75em;
      color: var(--text-color-light);
      text-transform: uppercase;
      margin-top: 10px;
      margin-bottom: 5px;
      text-align: center;
    }
    
    @media (min-width: 768px) {
      .dashboard-grid { grid-template-columns: repeat(6, 1fr); }
      .card.vuelos-realizados, .card.horas-mes, .card.horas-dia-semana, .card.vuelos-diarios, .card.horas-totales { grid-column: span 6; }
      .card.top-pilotos-semana, .card.top-landing-rate-card { grid-column: span 3; }
      .card.kpi-horas, .card.kpi-vuelos { grid-column: span 3; }
      .card.piloto-semana-l, .card.piloto-semana-m, .card.piloto-semana-h { grid-column: span 2; }
    }
    @media (min-width: 1280px) {
      .dashboard-grid { grid-template-columns: repeat(12, 1fr); }
      .card.vuelos-realizados { grid-column: 1 / 5; grid-row: 1 / 3; }
      .card.kpi-horas { grid-column: 5 / 7; grid-row: 1 / 2; }
      .card.kpi-vuelos { grid-column: 5 / 7; grid-row: 2 / 3; }
      .card.piloto-semana-l { grid-column: 7 / 9; grid-row: 1 / 2; }
      .card.piloto-semana-m { grid-column: 9 / 11; grid-row: 1 / 2; }
      .card.piloto-semana-h { grid-column: 11 / 13; grid-row: 1 / 2; }
      .card.top-pilotos-semana { grid-column: 7 / 10; grid-row: 2 / 3; }
      .card.top-landing-rate-card { grid-column: 10 / 13; grid-row: 2 / 3; }
      .card.horas-mes { grid-column: 1 / 7; grid-row: 3 / 4; }
      .card.horas-dia-semana { grid-column: 7 / 13; grid-row: 3 / 4; }
      .card.vuelos-diarios { grid-column: 1 / 7; grid-row: 4 / 5; }
      .card.horas-totales { grid-column: 7 / 13; grid-row: 4 / 5; }
    }
  </style>
  <?php apply_color_theme(); ?>
</head>
<body>
  <div class="container">
    <div class="dashboard-grid">
      <div class="card vuelos-realizados">
        <h3 class="card-title"><?= t('recent_flights') ?></h3>
        <div class="card-content">
          <table>
            <thead>
              <tr>
                <th></th>
                <th><?= t('col_date') ?></th>
                <th><?= t('col_pic') ?></th>
                <th><?= t('col_eqp') ?></th>
                <th><?= t('col_orig') ?></th>
                <th><?= t('col_dest') ?></th>
                <th><?= t('col_online') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recent_flights_result) {
                while ($flight = $recent_flights_result->fetch_assoc()):
                  $pilot_id = $flight['userId'];
                  $pilot_details = $pilots_details[$pilot_id] ?? null;
                  
                  $data_attributes = '';
                  if ($pilot_details) {
                      $data_attributes = 'data-pilot=\'' . htmlspecialchars(json_encode($pilot_details), ENT_QUOTES, 'UTF-8') . '\'';
                  }
              ?>
                <tr <?= $data_attributes ?> onclick="if (this.dataset.pilot) { window.location.href = `estatisticas_piloto.php?id=${encodeURIComponent(JSON.parse(this.dataset.pilot).id)}`; }">
                  <td>
                      <?php if (!empty($flight['network'])): ?>
                          <?php if ($flight['network'] === 'i'): ?>
                              <img src="assets/ivao_logo.jpg" alt="IVAO" title="IVAO" class="network-logo">
                          <?php elseif ($flight['network'] === 'v'): ?>
                              <img src="assets/vatsim_logo.jpg" alt="VATSIM" title="VATSIM" class="network-logo">
                          <?php endif; ?>
                      <?php endif; ?>
                  </td>
                  <td><?= (new DateTime($flight['createdAt']))->format('d/m') ?></td>
                  <td>
                      <?php if ($pilot_details): ?>
                          <?= htmlspecialchars($pilot_details['name']) ?>
                      <?php else: ?>
                          <span class="pic-visitante"><?= t('visitor') ?></span>
                      <?php endif; ?>
                  </td>
                  <td><?= (!empty($flight['EQP']) && strtolower($flight['EQP']) !== 'null') ? htmlspecialchars($flight['EQP']) : 'ZZZZ' ?></td>
                  <td><?= (!empty($flight['ORIG']) && strtolower($flight['ORIG']) !== 'null') ? htmlspecialchars($flight['ORIG']) : 'ZZZZ' ?></td>
                  <td><?= (!empty($flight['DEST']) && strtolower($flight['DEST']) !== 'null') ? htmlspecialchars($flight['DEST']) : 'ZZZZ' ?></td>
                  <td><?= format_seconds_to_hm($flight['time']) ?></td>
                </tr>
              <?php endwhile;
              } ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card kpi-horas">
        <h3 class="card-title"><?= t('total_hours') ?></h3>
        <div class="card-content kpi-value"><?= $kpi_total_hours ?></div>
      </div>
      <div class="card kpi-vuelos">
        <h3 class="card-title"><?= t('total_flights') ?></h3>
        <div class="card-content kpi-value"><?= $kpi_total_flights ?></div>
      </div>
      <?php foreach (['L', 'M', 'H'] as $cat): ?>
        <div class="card piloto-semana-<?= strtolower($cat) ?>">
          <h3 class="card-title"><?= t('pilot_of_the_week') . $cat ?></h3>
          <div class="card-content champion-pilot">
            <?php if ($weekly_champions[$cat]):
              $pilot_id = $weekly_champions[$cat]['userId'];
              $pilot_name = $pilots_map[$pilot_id] ?? 'Desconhecido';
              $pilot_photo = $pilots_photos[$pilot_id] ?? 'piloto.png';
              $pilot_hours = number_format(format_seconds_to_float($weekly_champions[$cat]['total_time']), 2);
            ?>
              <img src="<?= htmlspecialchars($pilot_photo) ?>" onerror="this.onerror=null; this.src='assets/images/piloto.png';">
              <a href="estatisticas_piloto.php?id=<?= urlencode($pilot_id) ?>" class="pilot-link">
                  <div class="name"><?= htmlspecialchars($pilot_name) ?></div>
              </a>
              <div class="hours"><?= $pilot_hours ?></div>
            <?php else: ?><p style="margin:auto;color:#999;"><?= t('not_available_abbr') ?></p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="card top-pilotos-semana">
        <h3 class="card-title"><?= t('hours_this_week') ?></h3>
        <div class="card-content">
          <ul class="top-list">
            <?php if ($top_weekly_result) {
              while ($pilot = $top_weekly_result->fetch_assoc()):
                $pilot_id = $pilot['userId'];
                $pilot_name = $pilots_map[$pilot_id] ?? t('not_available_abbr');
                $pilot_photo = $pilots_photos[$pilot_id] ?? 'piloto.png';
            ?>
                <li>
                  <div class="pilot-details">
                    <img src="<?= htmlspecialchars($pilot_photo) ?>" class="pilot-photo" onerror="this.onerror=null; this.src='assets/images/piloto.png';">
                    <a href="estatisticas_piloto.php?id=<?= urlencode($pilot_id) ?>" class="pilot-link">
                      <span class="name"><?= htmlspecialchars($pilot_name) ?></span>
                    </a>
                  </div>
                  <span class="hours"><?= number_format(format_seconds_to_float($pilot['total_seconds']), 2) ?></span>
                </li>
            <?php endwhile;
            } ?>
          </ul>
        </div>
      </div>
      
      <div class="card top-landing-rate-card">
        <h3 class="card-title"><?= t('top_landing_rate') ?></h3>
        <div class="card-content">
          <ul class="top-list">
            <?php if (!empty($top_landing_rate_result_by_cat)): // Usando sintaxe de dois pontos (:)
              foreach ($top_landing_rate_result_by_cat as $pilot):
                $pilot_id = $pilot['userId'];
                $pilot_name = $pilots_map[$pilot_id] ?? t('visitor');
                $pilot_photo = $pilots_photos[$pilot_id] ?? 'piloto.png';
                $category_code = $pilot['category_code'];
                
                // 1. Obtém os valores de pouso e aeronave
                $landing_vs_avg_raw = $pilot['best_landing_vs_signed'] ?? 9999; /* CORRIGIDO AQUI: USANDO best_landing_vs_signed */
                $landing_vs_abs = abs($landing_vs_avg_raw); 
                $landing_vs_display = number_format($landing_vs_abs, 1, '.', '');
                $landing_vs_for_color = round($landing_vs_abs);
                $aircraft_model = $pilot['main_aircraft_model'] ?? t('not_available_abbr'); 
                
                // 2. Determina os limites de pouso com base na categoria
                $thresholds = $landing_thresholds[$category_code] ?? $landing_thresholds['M'];

                // 3. Lógica de cores baseada na categoria
                $color = '#dc3545'; // Vermelho (Hard)

                if ($landing_vs_for_color <= $thresholds['Green_Max']) {
                    $color = '#48c774'; // Verde (Smooth)
                } elseif ($landing_vs_for_color <= $thresholds['Yellow_Max']) {
                    $color = '#ffc107'; // Amarelo (Medium)
                } 
                
                // 4. Lógica de formatação do nome (Primeiro nome + Aeronave + Categoria)
                $name_parts = explode(' ', $pilot_name);
                $first_name = $name_parts[0];
                $aircraft_display = "";
                
                if ($aircraft_model !== t('not_available_abbr')) {
                    $aircraft_display = " (" . htmlspecialchars($aircraft_model) . ")";
                }

                // ADIÇÃO DA CATEGORIA NA EXIBIÇÃO COM ESTILO ELEGANCE
                $category_badge = ' <span style="background-color: #e0e0e0; color: #333; padding: 1px 4px; border-radius: 3px; font-weight: 700; font-size: 0.8em; margin-left: 5px;">' . $category_code . '</span>';
                
                $pilot_name_display = htmlspecialchars($first_name) . $aircraft_display;

            ?>
              <li>
                <div class="pilot-details">
                    <img src="<?= htmlspecialchars($pilot_photo) ?>" class="pilot-photo" onerror="this.onerror=null; this.src='assets/images/piloto.png';">
                    <a href="estatisticas_piloto.php?id=<?= urlencode($pilot_id) ?>" class="pilot-link">
                        <span class="name"><?= $pilot_name_display . $category_badge ?></span>
                    </a>
                </div>
                <span class="hours" style="font-weight: 700; color: <?= $color ?>;"><?= $landing_vs_display ?> fpm</span>
              </li>
            <?php endforeach; // Fechamento do foreach ?>
            <?php else: // Início do else ?>
              <li><?= t('no_data') ?></li>
            <?php endif; // Fechamento do if ?>
          </ul>
        </div>
      </div>
      <div class="card horas-mes">
        <h3 class="card-title"><?= t('accumulated_hours_month') ?></h3>
        <div class="card-content chart-container"><canvas id="graficoHorasMes"></canvas></div>
      </div>
      <div class="card horas-dia-semana">
        <h3 class="card-title"><?= t('total_hours_by_day') ?></h3>
        <div class="card-content chart-container"><canvas id="graficoHorasDiaSemana"></canvas></div>
      </div>
      <div class="card vuelos-diarios">
        <h3 class="card-title"><?= t('daily_flights_count') ?></h3>
        <div class="card-content chart-container"><canvas id="graficoVuelosDiarios"></canvas></div>
      </div>
      <div class="card horas-totales">
        <h3 class="card-title"><?= t('total_hours_top_5') ?></h3>
        <div class="card-content chart-container"><canvas id="graficoHorasTotais"></canvas></div>
      </div>
    </div>
  </div>
  
  <div id="card-piloto-hover">
      <div class="content">
          <img src="piloto.png" alt="Foto do Piloto">
          <div class="name"></div>
          <div class="stat-info">
              <div class="stat-item">
                  <span class="stat-label"><?= t('flights_label') ?></span>
                  <span class="stat-value" id="card-vuelos">0</span>
              </div>
              <div class="stat-item">
                  <span class="stat-label"><?= t('hours_label') ?></span>
                  <span class="stat-value" id="card-horas">0</span>
              </div>
          </div>
          <div class="monthly-chart-title"><?= t('hours_in_month') ?></div>
          <div class="monthly-chart-container"><svg class="monthly-chart"></svg></div>
          </div>
  </div>

  <script>
    let tooltipTimeout;

    function createMiniChart(data, element) {
      if (!data || data.length === 0) {
        element.innerHTML = '<span style="font-size: 0.8em; color: #999;">Sem dados neste mês.</span>';
        return;
      }

      const svgWidth = element.clientWidth;
      const svgHeight = element.clientHeight;
      const maxVal = Math.max(...data);
      const points = data.map((val, i) => {
        const x = (i / (data.length - 1)) * svgWidth;
        const y = svgHeight - (val / maxVal) * svgHeight;
        return `${x},${y}`;
      }).join(' ');

      const svgContent = `
        <svg width="${svgWidth}" height="${svgHeight}">
          <polyline points="${points}" fill="none" stroke="var(--primary-color, #0d6efd)" stroke-width="2" />
          <path d="M0,${svgHeight} L${points} L${svgWidth},${svgHeight} Z" fill="var(--primary-color, #0d6efd)" opacity="0.2"/>
        </svg>
      `;
      element.innerHTML = svgContent;
    }

    function showPilotCard(e, element) {
        clearTimeout(tooltipTimeout);

        const pilotData = element.dataset.pilot;
        if (!pilotData) return;

        const pilot = JSON.parse(pilotData);
        const card = document.getElementById('card-piloto-hover');
        const img = card.querySelector('img');
        const nameDiv = card.querySelector('.name');
        const cardVuelos = document.getElementById('card-vuelos');
        const cardHoras = document.getElementById('card-horas');
        const chartContainer = card.querySelector('.monthly-chart');
        
        img.src = pilot.photo || 'piloto.png';
        img.onerror = function() { this.src = 'piloto.png'; };
        nameDiv.textContent = pilot.name;
        cardVuelos.textContent = pilot.total_flights;
        cardHoras.textContent = pilot.total_hours;
        
        createMiniChart(pilot.monthly_data, chartContainer);

        card.style.left = `${e.pageX + 15}px`;
        card.style.top = `${e.pageY - 15}px`;

        card.style.display = 'block';
        setTimeout(() => {
            card.style.opacity = 1;
        }, 10);
    }

    function hidePilotCard() {
        tooltipTimeout = setTimeout(() => {
            const card = document.getElementById('card-piloto-hover');
            card.style.opacity = 0;
            
            setTimeout(() => {
                card.style.display = 'none';
            }, 300);
        }, 300);
    }

    function hexToRgba(hex, alpha) {
        // Remove o # se existir
        hex = hex.replace('#', '');
        // Converte 3 dígitos para 6 dígitos
        if (hex.length === 3) {
            hex = hex.split('').map(h => h + h).join('');
        }
        const r = parseInt(hex.substring(0,2), 16);
        const g = parseInt(hex.substring(2,4), 16);
        const b = parseInt(hex.substring(4,6), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    window.addEventListener('DOMContentLoaded', (event) => {
        const tableRows = document.querySelectorAll('.vuelos-realizados tbody tr');
        tableRows.forEach(row => {
            if (row.dataset.pilot) {
                row.addEventListener('mousemove', (e) => showPilotCard(e, row));
                row.addEventListener('mouseout', hidePilotCard);
            }
        });

        const card = document.getElementById('card-piloto-hover');
        card.addEventListener('mouseover', () => clearTimeout(tooltipTimeout));
        card.addEventListener('mouseout', hidePilotCard);

        const style = getComputedStyle(document.body);
        const chartTextColor = style.getPropertyValue('--text-color').trim();
        const chartGridColor = style.getPropertyValue('--border-color').trim();
        const primaryColor = style.getPropertyValue('--primary-color').trim();
        const secondaryColor = '#cccccc';

        const defaultChartOptions = {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { top: 10, bottom: 0 } },
          scales: {
            y: { ticks: { color: chartTextColor, font: { size: 10 } }, grid: { color: chartGridColor }, beginAtZero: true },
            x: { ticks: { color: chartTextColor, font: { size: 10 } }, grid: { display: false } }
          },
          plugins: { legend: { labels: { color: chartTextColor, boxWidth: 10, font: { size: 11 } } } }
        };

        const todayIndexMonth = new Date().getDate() - 1; // -1 porque labels começa em 0

        new Chart(document.getElementById('graficoHorasMes'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels_dias_mes) ?>,
                datasets: [{
                    label: '<?= t('current_month') ?>',
                    data: <?= json_encode($chart_data_horas_mes_actual) ?>,
                    borderColor: primaryColor,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                },{
                    label: '<?= t('previous_month') ?>',
                    data: <?= json_encode($chart_data_horas_mes_anterior) ?>,
                    borderColor: secondaryColor,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                }]
            },
            options: {
                ...defaultChartOptions,
                plugins: {
                    legend: { display: true, position: 'top', align: 'end' }
                },
                scales: {
                    x: {
                        ticks: {
                            color: function(context) {
                                return context.index === todayIndexMonth ? primaryColor : chartTextColor;
                            },
                            font: function(context) {
                                return {
                                    weight: context.index === todayIndexMonth ? 'bold' : 'normal',
                                    size: 12
                                };
                            }
                        },
                        grid: {
                            display: true,
                            color: function(context) {
                                if (context.index === todayIndexMonth) {
                                    return hexToRgba(primaryColor, 0.05); // linha base quase transparente
                                }
                                return '#e0e0e0'; // linhas dos outros dias
                            },
                            borderColor: '#ccc',
                            drawTicks: false
                        }
                    },
                    y: {
                        grid: {
                            color: '#e0e0e0'
                        }
                    }
                }
            },
            plugins: [
                {
                    beforeDraw: (chart) => {
                        const ctx = chart.ctx;
                        const xScale = chart.scales.x;
                        const yScale = chart.scales.y;
                        const x = xScale.getPixelForTick(todayIndexMonth);

                        ctx.save();
                        ctx.strokeStyle = hexToRgba(primaryColor, 0.2); // traço principal com opacidade
                        ctx.lineWidth = 2;
                        ctx.shadowColor = primaryColor;   // cor do glow
                        ctx.shadowBlur = 10;              // intensidade do glow
                        ctx.beginPath();
                        ctx.moveTo(x, yScale.top);
                        ctx.lineTo(x, yScale.bottom);
                        ctx.stroke();
                        ctx.restore();
                    }
                }
            ]
        });

        const todayIndex = new Date().getDay(); // 0 = Domingo, 1 = Segunda ... 6 = Sábado

        function hexToRgba(hex, alpha) {
            hex = hex.replace('#','');
            if (hex.length === 3) hex = hex.split('').map(h => h+h).join('');
            const r = parseInt(hex.substring(0,2),16);
            const g = parseInt(hex.substring(2,4),16);
            const b = parseInt(hex.substring(4,6),16);
            return `rgba(${r},${g},${b},${alpha})`;
        }

        new Chart(document.getElementById('graficoHorasDiaSemana'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels_dias_semana) ?>,
                datasets: [{
                    label: '<?= t('current_week') ?>',
                    data: <?= json_encode($chart_data_horas_semana_atual_corrigido) ?>,
                    borderColor: primaryColor,
                    tension: 0.3,
                    borderWidth: 2
                },{
                    label: '<?= t('previous_week') ?>',
                    data: <?= json_encode($chart_data_horas_semana_anterior_corrigido) ?>,
                    borderColor: secondaryColor,
                    tension: 0.3,
                    borderDash: [5,5],
                    borderWidth: 2
                }]
            },
            options: {
                ...defaultChartOptions,
                plugins: {
                    legend: { display: true, position: 'top', align: 'end' }
                },
                scales: {
                    x: {
                        ticks: {
                            color: function(context) {
                                return context.index === todayIndex ? primaryColor : '#666';
                            },
                            font: function(context) {
                                return {
                                    weight: context.index === todayIndex ? 'bold' : 'normal',
                                    size: 12
                                };
                            }
                        },
                        grid: {
                            display: true,
                            color: function(context) {
                                if (context.index === todayIndex) {
                                    return hexToRgba(primaryColor, 0.05); // linha base quase transparente
                                }
                                return '#e0e0e0'; // linhas dos outros dias
                            },
                            borderColor: '#ccc',
                            drawTicks: false
                        }
                    },
                    y: {
                        grid: {
                            color: '#e0e0e0'
                        }
                    }
                }
            },
            plugins: [
                {
                    beforeDraw: (chart) => {
                        const ctx = chart.ctx;
                        const xScale = chart.scales.x;
                        const yScale = chart.scales.y;
                        const x = xScale.getPixelForTick(todayIndex);

                        ctx.save();
                        ctx.strokeStyle = hexToRgba(primaryColor, 0.2); // traço principal com opacidade
                        ctx.lineWidth = 2;
                        ctx.shadowColor = primaryColor;   // cor do glow
                        ctx.shadowBlur = 10;              // intensidade do glow
                        ctx.beginPath();
                        ctx.moveTo(x, yScale.top);
                        ctx.lineTo(x, yScale.bottom);
                        ctx.stroke();
                        ctx.restore();
                    }
                }
            ]
        });


        new Chart(document.getElementById('graficoVuelosDiarios'), {
          type: 'bar',
          data: {
            labels: <?= json_encode($chart_labels_dias_mes) ?>,
            datasets: [{
              label: '<?= t('current_month') ?>',
              data: <?= json_encode($chart_data_vuelos_mes_actual) ?>,
              backgroundColor: primaryColor
            }, {
              label: '<?= t('previous_month') ?>',
              data: <?= json_encode($chart_data_vuelos_mes_anterior) ?>,
              backgroundColor: secondaryColor
            }]
          },
          options: { ...defaultChartOptions, plugins: { legend: { display: true, position: 'top', align: 'end' } } }
        });

        new Chart(document.getElementById('graficoHorasTotais'), {
          type: 'bar',
          data: {
            labels: <?= json_encode($chart_labels_top_pilots) ?>,
            datasets: [{
              label: '<?= t('total_hours') ?>',
              data: <?= json_encode($chart_data_top_pilots_hours) ?>,
              backgroundColor: primaryColor
            }]
          },
          options: { ...defaultChartOptions, indexAxis: 'y', plugins: { legend: { display: false } } }
        });
    });
  </script>
</body>
</html>