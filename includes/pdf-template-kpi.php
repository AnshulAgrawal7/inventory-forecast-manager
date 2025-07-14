<?php
// mPDF autoload einbinden (Pfad ggf. anpassen)
require_once IFM_PLUGIN_DIR . 'vendor/autoload.php';

// --- KPI/Forecast Berechnung wie gehabt ---
$selected = $_GET['range'] ?? '7d';
$from_custom = $_GET['from'] ?? '';
$to_custom = $_GET['to'] ?? '';

if ($selected !== 'custom') {
    $range_days = match ($selected) {
        '30d' => 30,
        '90d' => 90,
        default => 7,
    };
    $from_date = date('Y-m-d', strtotime("-$range_days days"));
    $to_date = date('Y-m-d');
} else {
    $from_date = $from_custom ?: date('Y-m-d', strtotime("-7 days"));
    $to_date = $to_custom ?: date('Y-m-d');
}

$online_sales   = IFM_Forecast::get_wc_sales($from_date, $to_date);
$offline_sales  = IFM_Forecast::get_offline_sales($from_date, $to_date);
$total_revenue  = $online_sales['revenue'] + $offline_sales['revenue'];
$total_orders   = $online_sales['count'] + $offline_sales['count'];
$aov            = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$online_items   = IFM_Forecast::get_wc_total_items($from_date, $to_date);
$offline_items  = IFM_Forecast::get_offline_total_items($from_date, $to_date);
$total_items    = $online_items + $offline_items;
$avg_items_per_order = $total_orders > 0 ? $total_items / $total_orders : 0;
$product_query  = new WC_Product_Query(['limit' => -1, 'status' => 'publish']);
$all_products   = $product_query->get_products();
$stock_total = 0; $stock_out = 0;
foreach ($all_products as $p) {
    if ($p->get_stock_quantity() !== null) {
        $stock_total++;
        if ($p->get_stock_quantity() <= 0) $stock_out++;
    }
}
$out_of_stock_rate = $stock_total > 0 ? round($stock_out / $stock_total * 100, 1) : 0;
$bestseller        = IFM_Forecast::get_bestseller($from_date, $to_date);

$dates = [];
$period = new DatePeriod(
    new DateTime($from_date),
    new DateInterval('P1D'),
    (new DateTime($to_date))->modify('+1 day')
);
$total_sales_data = [];
$online_sales_data = [];
$offline_sales_data = [];
foreach ($period as $date) {
    $dates[] = $date->format('Y-m-d');
    $online  = IFM_Forecast::get_online_sales_for_day($date->format('Y-m-d'));
    $offline = IFM_Forecast::get_offline_sales_for_day($date->format('Y-m-d'));
    $online_sales_data[] = $online;
    $offline_sales_data[] = $offline;
    $total_sales_data[] = $online + $offline;
}
$forecast_days = 7;
$forecast_source_days = min(count($dates), 28);
$forecast_start = max(0, count($dates) - $forecast_source_days);
$used_revenues = array_slice($total_sales_data, $forecast_start, $forecast_source_days);
$days_real_orders = $forecast_source_days > 0 ? $forecast_source_days : 1;
$mean_per_day_revenue = ($forecast_source_days > 0) ? (array_sum($used_revenues) / $forecast_source_days) : 0;
$mean_per_day_orders = $days_real_orders > 0 ? $total_orders / $days_real_orders : 0;
$forecast_revenue = round($mean_per_day_revenue * $forecast_days, 2);
$forecast_orders = round($mean_per_day_orders * $forecast_days);

$sales_chart_img = $_POST['sales_chart_img'] ?? '';

// HTML Inhalt für das PDF:
$html = '
<style>
body { font-family: Arial,sans-serif; color:#222; }
.kpi-header { font-size:1.6em; font-weight:bold; margin-bottom:18px; color:#19549a; }
.kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:25px; }
.kpi-box { background:#f7fafc; border-radius:11px; box-shadow:0 1px 4px #eee; padding:18px 12px 10px 12px; text-align:center; }
.kpi-title { font-size:1.1em; color:#555; margin-bottom:7px; }
.kpi-value { font-size:1.45em; font-weight:700; margin-bottom:3px; }
.forecast-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px;}
.forecast-box { background:#e8f3fe; border-radius:11px; box-shadow:0 1px 4px #eee; padding:18px 12px 10px 12px; text-align:center;}
.forecast-title { font-size:1.08em; color:#1d3d57; margin-bottom:5px;}
.forecast-value { font-size:1.4em; font-weight:bold;}
</style>
<div class="kpi-header">KPIs</div>
<div class="kpi-grid">
    <div class="kpi-box">
        <div class="kpi-title">Gesamtumsatz</div>
        <div class="kpi-value">'.number_format($total_revenue, 2, ",", ".").' €</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-title">Ø Bestellwert</div>
        <div class="kpi-value">'.number_format($aov, 2, ",", ".").' €</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-title">Out-of-Stock-Rate</div>
        <div class="kpi-value">'.$out_of_stock_rate.' %</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-title">Verkäufe</div>
        <div class="kpi-value">'.intval($total_orders).'</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-title">Ø Artikel/Best.</div>
        <div class="kpi-value">'.number_format($avg_items_per_order, 2, ",", ".").'</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-title">Bestseller</div>
        <div class="kpi-value">'.htmlspecialchars($bestseller).'</div>
    </div>
</div>';

if ($sales_chart_img) {
    $html .= '<div style="margin:18px auto 25px auto; text-align:center;">
        <img src="'.$sales_chart_img.'" style="max-width:90%;max-height:260px;" />
        <div style="font-size:0.96em;color:#555;margin-top:4px;">Umsatzentwicklung</div>
    </div>';
}

$html .= '
<div class="kpi-header" style="font-size:1.32em; margin-bottom:13px; margin-top:10px;">Forecast</div>
<div class="forecast-grid">
    <div class="forecast-box">
        <div class="forecast-title">Prognose Umsatz (nächste '.$forecast_days.' Tage)</div>
        <div class="forecast-value">'.number_format($forecast_revenue, 2, ",", ".").' €</div>
    </div>
    <div class="forecast-box" style="background:#f5ffe3;">
        <div class="forecast-title">Prognose Verkäufe (nächste '.$forecast_days.' Tage)</div>
        <div class="forecast-value">'.intval($forecast_orders).'</div>
    </div>
</div>';

// mPDF Objekt erzeugen & Einstellungen
$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'orientation' => 'P',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 18,
    'margin_bottom' => 10,
]);

$mpdf->SetTitle('KPIs & Forecast Export');
$mpdf->WriteHTML($html);
$mpdf->Output('ifm-kpi-forecast.pdf', \Mpdf\Output\Destination::INLINE);
exit;
