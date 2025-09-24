<?php
// =================================================================
// 0. CARREGAR CONFIGURAÇÕES DE IDIOMA E TEMA
// =================================================================
require_once __DIR__ . '/../src/config_loader.php';

// =================================================================
// 1. CONFIGURAÇÃO E CONEXÃO COM A BASE DE DADOS
// =================================================================
require_once __DIR__ . '/../../config_db.php'; // Ajuste o caminho conforme necessário

$conn_pilotos = criar_conexao(DB_PILOTOS_NAME, DB_PILOTOS_USER, DB_PILOTOS_PASS);
$conn_voos = criar_conexao(DB_VOOS_NAME, DB_VOOS_USER, DB_VOOS_PASS);

function format_seconds_to_hm($seconds)
{
  if (!$seconds || $seconds <= 0) return '00:00';
  $h = floor($seconds / 3600);
  $m = floor(($seconds % 3600) / 60);
  return sprintf('%02d:%02d', $h, $m);
}

// =================================================================
// FUNÇÃO PARA BUSCAR DADOS DIÁRIOS PARA O GRÁFICO
// =================================================================
function get_pilot_daily_stats_for_month($conn, $interval_months, $aggregate_expression, $sql_where_clause, $bind_params, $bind_types)
{
  if ($interval_months == 0) {
    $start_date_sql = "DATE_FORMAT(NOW(), '%Y-%m-01')";
    $end_date_sql = "DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')";
  } else {
    $start_date_sql = "DATE_FORMAT(NOW() - INTERVAL {$interval_months} MONTH, '%Y-%m-01')";
    $end_date_sql = "DATE_FORMAT(NOW() - INTERVAL " . ($interval_months - 1) . " MONTH, '%Y-%m-01')";
  }
  
  $sql = "SELECT DAY(createdAt) as day, {$aggregate_expression} as value FROM ".DB_VOOS_NAME.".voos "
       . $sql_where_clause
       . " AND createdAt >= {$start_date_sql} AND createdAt < {$end_date_sql} "
       . "GROUP BY day ORDER BY day ASC";

  $stmt = $conn->prepare($sql);
  if (!empty($bind_params)) {
      $stmt->bind_param($bind_types, ...$bind_params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  
  $daily_data = array_fill(1, 31, 0);
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $daily_data[$row['day']] = $row['value'];
    }
  }
  $stmt->close();
  return $daily_data;
}


// =================================================================
// 2. BUSCAR DADOS E PROCESSAR FILTROS
// =================================================================
$pilot_user_id = $_GET['id'] ?? null;
if (!$pilot_user_id) {
    die("ID de piloto não fornecido.");
}

$network_filter = $_GET['rede'] ?? 'geral'; 

$stmt_piloto = $conn_pilotos->prepare("SELECT CONCAT(first_name, ' ', last_name) as display_name, foto_perfil, vatsim_id, ivao_id FROM Dados_dos_Pilotos WHERE vatsim_id = ? OR ivao_id = ?");
$stmt_piloto->bind_param("ss", $pilot_user_id, $pilot_user_id);
$stmt_piloto->execute();
$piloto_info = $stmt_piloto->get_result()->fetch_assoc();
$stmt_piloto->close();

if (!$piloto_info) {
    die("Piloto não encontrado.");
}

// Prepara a cláusula WHERE e os parâmetros para todas as consultas de voos
$vatsim_id = $piloto_info['vatsim_id'];
$ivao_id = $piloto_info['ivao_id'];
$sql_where_clause = "";
$bind_params = [];
$bind_types = "";
$titulo_rede = "";

switch ($network_filter) {
    case 'ivao':
        $sql_where_clause = "WHERE userId = ?";
        $bind_params[] = $ivao_id;
        $bind_types = "s";
        $titulo_rede = t('network_ivao');
        break;
    case 'vatsim':
        $sql_where_clause = "WHERE userId = ?";
        $bind_params[] = $vatsim_id;
        $bind_types = "s";
        $titulo_rede = t('network_vatsim');
        break;
    default: // 'geral'
        $sql_where_clause = "WHERE userId IN (?, ?)";
        $bind_params[] = $vatsim_id;
        $bind_params[] = $ivao_id;
        $bind_types = "ss";
        $titulo_rede = t('network_general');
        break;
}

// =================================================================
// LÓGICA PARA PROCESSAR DADOS DO GRÁFICO
// =================================================================
$daily_seconds_actual = get_pilot_daily_stats_for_month($conn_voos, 0, 'SUM(time)', $sql_where_clause, $bind_params, $bind_types);
$daily_seconds_anterior = get_pilot_daily_stats_for_month($conn_voos, 1, 'SUM(time)', $sql_where_clause, $bind_params, $bind_types);

$current_day = (int)date('d');
$chart_data_horas_mes_actual_raw = [];
$running_total_actual = 0;
for ($day = 1; $day <= $current_day; $day++) {
    $running_total_actual += $daily_seconds_actual[$day];
    $chart_data_horas_mes_actual_raw[] = round($running_total_actual / 3600, 1);
}
$chart_data_horas_mes_actual = array_merge($chart_data_horas_mes_actual_raw, array_fill(0, 31 - $current_day, null));

$chart_data_horas_mes_anterior = [];
$running_total_anterior = 0;
foreach ($daily_seconds_anterior as $seconds) {
  $running_total_anterior += $seconds;
  $chart_data_horas_mes_anterior[] = round($running_total_anterior / 3600, 1);
}
$chart_labels_dias_mes = range(1, 31);

// =================================================================
// Consultas para os cards de estatísticas
// =================================================================
$query_stats = "SELECT COUNT(*) as total_flights, SUM(time) as total_seconds, AVG(time) as avg_seconds FROM voos " . $sql_where_clause;
$stmt_stats = $conn_voos->prepare($query_stats);
if (!empty($bind_params)) { $stmt_stats->bind_param($bind_types, ...$bind_params); }
$stmt_stats->execute();
$piloto_stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();
$query_acft = "SELECT flightPlan_aircraft_model as aircraft, COUNT(*) as count FROM voos " . $sql_where_clause . " AND flightPlan_aircraft_model IS NOT NULL AND flightPlan_aircraft_model != '' GROUP BY aircraft ORDER BY count DESC LIMIT 1";
$stmt_acft = $conn_voos->prepare($query_acft);
if (!empty($bind_params)) { $stmt_acft->bind_param($bind_types, ...$bind_params); }
$stmt_acft->execute();
$top_aircraft = $stmt_acft->get_result()->fetch_assoc();
$stmt_acft->close();
$query_flights = "SELECT flightPlan_aircraft_model as EQP, flightPlan_departureId as ORIG, flightPlan_arrivalId as DEST, time, createdAt FROM voos " . $sql_where_clause . " ORDER BY createdAt DESC LIMIT 10";
$stmt_flights = $conn_voos->prepare($query_flights);
if (!empty($bind_params)) { $stmt_flights->bind_param($bind_types, ...$bind_params); }
$stmt_flights->execute();
$recent_flights_result = $stmt_flights->get_result();
$stmt_flights->close();
$query_longest = "SELECT flightPlan_departureId as orig, flightPlan_arrivalId as dest, time FROM voos " . $sql_where_clause . " ORDER BY time DESC LIMIT 1";
$stmt_longest = $conn_voos->prepare($query_longest);
if (!empty($bind_params)) { $stmt_longest->bind_param($bind_types, ...$bind_params); }
$stmt_longest->execute();
$longest_flight = $stmt_longest->get_result()->fetch_assoc();
$stmt_longest->close();
$query_cat = "SELECT wakeTurbulence as category, COUNT(*) as count FROM voos " . $sql_where_clause . " GROUP BY category";
$stmt_cat = $conn_voos->prepare($query_cat);
if (!empty($bind_params)) { $stmt_cat->bind_param($bind_types, ...$bind_params); }
$stmt_cat->execute();
$category_results = $stmt_cat->get_result();
$category_counts = ['L' => 0, 'M' => 0, 'H' => 0];
while($row = $category_results->fetch_assoc()) { if (isset($category_counts[$row['category']])) { $category_counts[$row['category']] = $row['count']; } }
$stmt_cat->close();
$query_dep = "SELECT flightPlan_departureId as airport, COUNT(*) as count FROM voos " . $sql_where_clause . " AND flightPlan_departureId IS NOT NULL AND flightPlan_departureId != '' GROUP BY airport ORDER BY count DESC LIMIT 5";
$stmt_dep = $conn_voos->prepare($query_dep);
if (!empty($bind_params)) { $stmt_dep->bind_param($bind_types, ...$bind_params); }
$stmt_dep->execute();
$top_departures = $stmt_dep->get_result();
$stmt_dep->close();
$query_arr = "SELECT flightPlan_arrivalId as airport, COUNT(*) as count FROM voos " . $sql_where_clause . " AND flightPlan_arrivalId IS NOT NULL AND flightPlan_arrivalId != '' GROUP BY airport ORDER BY count DESC LIMIT 5";
$stmt_arr = $conn_voos->prepare($query_arr);
if (!empty($bind_params)) { $stmt_arr->bind_param($bind_types, ...$bind_params); }
$stmt_arr->execute();
$top_arrivals = $stmt_arr->get_result();
$stmt_arr->close();

$conn_pilotos->close();
$conn_voos->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('stats_for_pilot_title') . htmlspecialchars($piloto_info['display_name']) . " ($titulo_rede)" ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: var(--background-color); color: var(--text-color); margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .card { background-color: var(--card-background-color); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; }
        .profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .profile-header img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); }
        .profile-header h1 { margin: 0; font-size: 2em; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center; margin-bottom: 20px; }
        .stat-item { background-color: #f8f9fa; padding: 15px; border-radius: 8px; }
        .stat-item .label { font-size: 0.9em; color: var(--text-color-light); margin-bottom: 5px; text-transform: uppercase; }
        .stat-item .value { font-size: 1.8em; font-weight: 700; color: var(--primary-color); }
        h2 { border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-top: 0; font-size: 1.2em; text-transform: uppercase; color: var(--text-color-light); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 500; color: var(--text-color-light); }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .columns { display: flex; flex-wrap: wrap; gap: 20px; }
        .column { flex: 1; min-width: 250px; }
        .airport-list { list-style: none; padding-left: 0; margin: 0; }
        .airport-list li { display: flex; justify-content: space-between; padding: 8px 4px; border-bottom: 1px solid #eee; font-size: 0.9em; }
        .airport-list li:last-child { border-bottom: none; }
        .airport-list .icao { font-weight: 700; }
        .airport-list .count { color: var(--text-color-light); }
        .network-filter { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
        .network-filter .btn-text { text-decoration: none; padding: 8px 16px; border-radius: 20px; background-color: #e9ecef; color: #495057; font-weight: 500; font-size: 0.9em; transition: all 0.2s ease-in-out; border: 2px solid #e9ecef; }
        .network-filter .btn-text:hover { background-color: #dee2e6; }
        .network-filter .btn-text.active { background-color: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .network-filter .btn-img { display: block; border: 2px solid transparent; border-radius: 8px; padding: 3px; line-height: 0; transition: all 0.2s ease-in-out; }
        .network-filter .btn-img img { height: 35px; width: auto; display: block; }
        .network-filter .btn-img:hover { transform: scale(1.05); border-color: #ccc; }
        .network-filter .btn-img.active { border-color: var(--primary-color); transform: scale(1.05); }
        .chart-container { position: relative; height: 250px; width: 100%; }
    </style>
    <?php apply_color_theme(); ?>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link"><?= t('back_to_dashboard') ?></a>

        <div class="card">
            <div class="profile-header">
                <img src="<?= htmlspecialchars($piloto_info['foto_perfil'] ?? 'assets/imagens/piloto.png') ?>" onerror="this.onerror=null; this.src='assets/imagens/piloto.png';">
                <h1><?= htmlspecialchars($piloto_info['display_name']) ?></h1>
            </div>
            <div class="network-filter">
                <a href="?id=<?= urlencode($pilot_user_id) ?>" class="btn-text <?= $network_filter === 'geral' ? 'active' : '' ?>"><?= t('network_general') ?></a>
                <?php if (!empty($ivao_id)): ?>
                    <a href="?id=<?= urlencode($pilot_user_id) ?>&rede=ivao" class="btn-img <?= $network_filter === 'ivao' ? 'active' : '' ?>" title="Filtrar por IVAO"><img src="assets/ivao.jpg" alt="IVAO Logo"></a>
                <?php endif; ?>
                <?php if (!empty($vatsim_id)): ?>
                    <a href="?id=<?= urlencode($pilot_user_id) ?>&rede=vatsim" class="btn-img <?= $network_filter === 'vatsim' ? 'active' : '' ?>" title="Filtrar por VATSIM"><img src="assets/vatsim.jpg" alt="VATSIM Logo"></a>
                <?php endif; ?>
            </div>
            <div class="stats-grid">
                <div class="stat-item"><div class="label"><?= t('total_hours') ?></div><div class="value"><?= floor(($piloto_stats['total_seconds'] ?? 0) / 3600) ?>h</div></div>
                <div class="stat-item"><div class="label"><?= t('total_flights') ?></div><div class="value"><?= $piloto_stats['total_flights'] ?? 0 ?></div></div>
                <div class="stat-item"><div class="label"><?= t('average_time') ?></div><div class="value"><?= format_seconds_to_hm($piloto_stats['avg_seconds'] ?? 0) ?></div></div>
                <div class="stat-item"><div class="label"><?= t('main_aircraft') ?></div><div class="value" style="font-size: 1.5em; padding-top: 5px;"><?= htmlspecialchars($top_aircraft['aircraft'] ?? t('not_available_abbr')) ?></div></div>
            </div>
        </div>

        <div class="card">
             <h2><?= t('performance') ?> (<?= $titulo_rede ?>)</h2>
             <div class="stats-grid">
                <div class="stat-item"><div class="label"><?= t('longest_flight') ?></div><?php if($longest_flight): ?><div class="value" style="font-size: 1.2em;"><?= htmlspecialchars($longest_flight['orig']) ?> &rarr; <?= htmlspecialchars($longest_flight['dest']) ?></div><div style="color: var(--primary-color); font-weight: 700;"><?= format_seconds_to_hm($longest_flight['time']) ?></div><?php else: ?><div class="value"><?= t('not_available_abbr') ?></div><?php endif; ?></div>
                <div class="stat-item"><div class="label"><?= t('light_flights') ?></div><div class="value"><?= $category_counts['L'] ?></div></div>
                <div class="stat-item"><div class="label"><?= t('medium_flights') ?></div><div class="value"><?= $category_counts['M'] ?></div></div>
                <div class="stat-item"><div class="label"><?= t('heavy_flights') ?></div><div class="value"><?= $category_counts['H'] ?></div></div>
            </div>
        </div>
        <div class="card">
            <div class="columns">
                <div class="column">
                    <h2><?= t('top_5_origins') ?> (<?= $titulo_rede ?>)</h2>
                    <ol class="airport-list"><?php if($top_departures->num_rows > 0): while($row = $top_departures->fetch_assoc()): ?><li><span class="icao"><?= htmlspecialchars($row['airport']) ?></span><span class="count"><?= $row['count'] ?> <?= t('flights_label_plural') ?></span></li><?php endwhile; else: ?><li><?= t('no_data') ?></li><?php endif; ?></ol>
                </div>
                <div class="column">
                    <h2><?= t('top_5_destinations') ?> (<?= $titulo_rede ?>)</h2>
                    <ol class="airport-list"><?php if($top_arrivals->num_rows > 0): while($row = $top_arrivals->fetch_assoc()): ?><li><span class="icao"><?= htmlspecialchars($row['airport']) ?></span><span class="count"><?= $row['count'] ?> <?= t('flights_label_plural') ?></span></li><?php endwhile; else: ?><li><?= t('no_data') ?></li><?php endif; ?></ol>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><?= t('accumulated_hours_month') ?> (<?= $titulo_rede ?>)</h2>
            <div class="chart-container">
                <canvas id="graficoHorasAcumuladasPiloto"></canvas>
            </div>
        </div>

        <div class="card">
            <h2><?= t('recent_flights') ?> (<?= $titulo_rede ?>)</h2>
            <table>
                <thead><tr><th><?= t('col_date') ?></th><th><?= t('col_eqp') ?></th><th><?= t('col_orig') ?></th><th><?= t('col_dest') ?></th><th><?= t('col_duration') ?></th></tr></thead>
                <tbody>
                    <?php if ($recent_flights_result->num_rows > 0): while ($flight = $recent_flights_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (new DateTime($flight['createdAt']))->format('d/m/Y') ?></td><td><?= htmlspecialchars($flight['EQP']) ?></td><td><?= htmlspecialchars($flight['ORIG']) ?></td><td><?= htmlspecialchars($flight['DEST']) ?></td><td><?= format_seconds_to_hm($flight['time']) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center; color: #999;"><?= t('no_flights_for_network') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const style = getComputedStyle(document.body);
            const chartTextColor = style.getPropertyValue('--text-color').trim();
            const chartGridColor = style.getPropertyValue('--border-color').trim();
            const primaryColor = style.getPropertyValue('--primary-color').trim();
            const secondaryColor = '#cccccc';

            new Chart(document.getElementById('graficoHorasAcumuladasPiloto'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels_dias_mes) ?>,
                    datasets: [{
                        label: '<?= t('current_month') ?>',
                        data: <?= json_encode($chart_data_horas_mes_actual) ?>,
                        borderColor: primaryColor,
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 1
                    },
                    {
                        label: '<?= t('previous_month') ?>',
                        data: <?= json_encode($chart_data_horas_mes_anterior) ?>,
                        borderColor: secondaryColor,
                        tension: 0.3,
                        pointRadius: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: chartTextColor },
                            grid: { color: chartGridColor }
                        },
                        x: {
                            ticks: { color: chartTextColor },
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { color: chartTextColor, boxWidth: 12, font: { size: 12 } },
                            position: 'top',
                            align: 'end'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>