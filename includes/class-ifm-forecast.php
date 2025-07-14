<?php
class IFM_Forecast {

    public static function handle() {}

    public static function render_forecast() {
        $selected = $_GET['range'] ?? '7d';
        $from_custom = $_GET['from'] ?? '';
        $to_custom = $_GET['to'] ?? '';
        $options = [
            '7d' => 'Letzte 7 Tage',
            '30d' => 'Letzte 30 Tage',
            '90d' => 'Letzte 90 Tage',
            'custom' => 'Eigener Zeitraum'
        ];

        // Zeitbereich setzen
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

        // Chart-Daten vorbereiten
        $period = new DatePeriod(
            new DateTime($from_date),
            new DateInterval('P1D'),
            (new DateTime($to_date))->modify('+1 day')
        );
        $dates = [];
        $online_sales_data = [];
        $offline_sales_data = [];
        $total_sales_data = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
            $online = self::get_online_sales_for_day($date->format('Y-m-d'));
            $offline = self::get_offline_sales_for_day($date->format('Y-m-d'));
            $online_sales_data[] = $online;
            $offline_sales_data[] = $offline;
            $total_sales_data[] = $online + $offline;
        }

        // Kumulative Werte
        $online_cum = []; $offline_cum = []; $total_cum = [];
        $sum = 0;
        foreach ($online_sales_data as $i => $v) {
            $sum = ($i > 0 ? $online_cum[$i-1] : 0) + $v;
            $online_cum[] = round($sum, 2);
        }
        $sum = 0;
        foreach ($offline_sales_data as $i => $v) {
            $sum = ($i > 0 ? $offline_cum[$i-1] : 0) + $v;
            $offline_cum[] = round($sum, 2);
        }
        $sum = 0;
        foreach ($total_sales_data as $i => $v) {
            $sum = ($i > 0 ? $total_cum[$i-1] : 0) + $v;
            $total_cum[] = round($sum, 2);
        }

        // KPIs
        $online_sales = self::get_wc_sales($from_date, $to_date);
        $offline_sales = self::get_offline_sales($from_date, $to_date);
        $total_revenue = $online_sales['revenue'] + $offline_sales['revenue'];
        $total_orders = $online_sales['count'] + $offline_sales['count'];

        $aov = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        $online_items = self::get_wc_total_items($from_date, $to_date);
        $offline_items = self::get_offline_total_items($from_date, $to_date);
        $total_items = $online_items + $offline_items;
        $avg_items_per_order = $total_orders > 0 ? $total_items / $total_orders : 0;

        $product_query = new WC_Product_Query(['limit' => -1, 'status' => 'publish']);
        $all_products = $product_query->get_products();
        $stock_total = 0; $stock_out = 0;
        foreach ($all_products as $p) {
            if ($p->get_stock_quantity() !== null) {
                $stock_total++;
                if ($p->get_stock_quantity() <= 0) $stock_out++;
            }
        }
        $out_of_stock_rate = $stock_total > 0 ? round($stock_out / $stock_total * 100, 1) : 0;

        $bestseller = self::get_bestseller($from_date, $to_date);

        // ---------- FORECASTING (EXAKT DEINER LOGIK) ----------
        // Prognose basiert auf den letzten 28 Tagen (oder Zeitraum im Chart, falls weniger)
        $forecast_days = 7;
        $forecast_source_days = min(count($dates), 28); // max. 28 Tage, aber nicht mehr als aktuell ausgewählt
        $forecast_start = max(0, count($dates) - $forecast_source_days);
        $used_revenues = array_slice($total_sales_data, $forecast_start, $forecast_source_days);

        // Orders für die letzten Tage zählen (besser als Summe der nicht-Null-Umsätze!)
        $used_orders = 0;
        for ($i = $forecast_start; $i < count($dates); $i++) {
            $day_online_orders = self::get_wc_sales($dates[$i], $dates[$i]);
            $day_offline_orders = self::get_offline_sales($dates[$i], $dates[$i]);
            $used_orders += $day_online_orders['count'] + $day_offline_orders['count'];
        }
        $days_real_orders = $forecast_source_days > 0 ? $forecast_source_days : 1;
        $mean_per_day_revenue = ($forecast_source_days > 0) ? (array_sum($used_revenues) / $forecast_source_days) : 0;
        $mean_per_day_orders = $days_real_orders > 0 ? $used_orders / $days_real_orders : 0;
        $forecast_revenue = round($mean_per_day_revenue * $forecast_days, 2);
        $forecast_orders = round($mean_per_day_orders * $forecast_days);

        ?>
        <div class="wrap">
            <h1 style="margin-bottom:0.5em;">KPIs</h1>
            <form method="get" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="ifm-forecast-kpis">
                <label for="range">Zeitraum:</label>
                <select name="range" id="range" onchange="this.form.submit()">
                    <?php foreach ($options as $val => $label) {
                        echo '<option value="' . esc_attr($val) . '" ' . selected($selected, $val, false) . '>' . esc_html($label) . '</option>';
                    } ?>
                </select>
                <span id="custom-date-fields" style="display:<?= ($selected == 'custom') ? 'inline' : 'none' ?>">
                    &nbsp;Von: <input type="date" name="from" value="<?= esc_attr($from_date) ?>">
                    Bis: <input type="date" name="to" value="<?= esc_attr($to_date) ?>">
                    <button type="submit" class="button button-small">Anzeigen</button>
                </span>
            </form>
            <style>
                .ifm-kpi-cards {
                    display:flex;
                    gap:14px;
                    margin-bottom:24px;
                    flex-wrap:wrap;
                }
                .ifm-kpi-card {
                    flex:1;
                    min-width:200px;
                    background:#f7fafc;
                    border-radius:14px;
                    box-shadow:0 1px 4px #ddd;
                    padding:20px 16px;
                    text-align:center;
                    display:flex;
                    flex-direction:column;
                    justify-content:center;
                    height:100px;
                }
                .ifm-kpi-blue { background:#f7fafc; }
                .ifm-kpi-green { background:#e8fff0; }
                .ifm-kpi-yellow { background:#fff2ee; }
                .ifm-kpi-violet { background:#efefff; }
                .ifm-kpi-lightgreen { background:#eaffea; }
                .ifm-kpi-pink { background:#ffeafd; }
                .ifm-kpi-title {
                    font-size:2em;
                    font-weight:bold;
                    margin-bottom:0.5em;
                }
                .ifm-kpi-desc {
                    font-size:1.05em;
                    color:#444;
                }
                @media (max-width: 1100px) {
                    .ifm-kpi-card {height:auto;}
                }
                /* Pie-Chart Container */
                .ifm-pie-wrap {
                    flex:1;
                    min-width:240px; max-width:350px;
                    background:#fff; border-radius:14px;
                    box-shadow:0 1px 4px #eee;
                    padding:16px 10px 8px 10px;
                    display:flex; flex-direction:column; align-items:center; justify-content:center;
                    height:100%;
                }
                .ifm-pie-legend {
                    font-size:0.96em; margin-top:8px; display:flex; gap:14px; justify-content:center; width:100%;
                }
                /* Umsatzentwicklung und Pie-Chart gemeinsam zentrieren */
                .ifm-saleschart-center {
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                    width: 100%;
                    gap: 32px;
                    margin-bottom: 32px;
                    flex-wrap: wrap;
                }
            </style>
            <div class="ifm-kpi-cards">
                <div class="ifm-kpi-card ifm-kpi-blue">
                    <div class="ifm-kpi-title"><?= number_format($total_revenue, 2, ',', '.') ?> €</div>
                    <div class="ifm-kpi-desc">Gesamtumsatz</div>
                </div>
                <div class="ifm-kpi-card ifm-kpi-green">
                    <div class="ifm-kpi-title"><?= number_format($aov, 2, ',', '.') ?> €</div>
                    <div class="ifm-kpi-desc">Ø Bestellwert</div>
                </div>
                <div class="ifm-kpi-card ifm-kpi-yellow">
                    <div class="ifm-kpi-title"><?= $out_of_stock_rate ?> %</div>
                    <div class="ifm-kpi-desc">Out-of-Stock-Rate</div>
                </div>
                <div class="ifm-kpi-card ifm-kpi-violet">
                    <div class="ifm-kpi-title"><?= intval($total_orders) ?></div>
                    <div class="ifm-kpi-desc">Verkäufe</div>
                </div>
                <div class="ifm-kpi-card ifm-kpi-lightgreen">
                    <div class="ifm-kpi-title"><?= number_format($avg_items_per_order, 2, ',', '.') ?></div>
                    <div class="ifm-kpi-desc">Ø Artikel/Best.</div>
                </div>
                <div class="ifm-kpi-card ifm-kpi-pink">
                    <div class="ifm-kpi-title"><?= esc_html($bestseller) ?></div>
                    <div class="ifm-kpi-desc">Bestseller</div>
                </div>
            </div>
            <!-- Umsatztabellen und PieChart ZENTRIERT -->
            <div class="ifm-saleschart-center">
                <!-- Umsatzentwicklung (zentriert) -->
                <div style="flex:2.4; min-width:380px; background:#fff; border-radius:14px; box-shadow:0 1px 4px #eee; padding:18px 16px 8px 16px;">
                    <div style="font-weight:500; margin-bottom:8px; text-align:center;">Umsatzentwicklung</div>
                    <div style="display:flex; gap:8px; align-items:center; justify-content:center; margin-bottom:12px;">
                        <button type="button" id="chart-line" class="ifm-charttype-btn selected" title="Liniendiagramm" style="padding:3px 12px;">
                            <svg width="24" height="20"><polyline points="2,17 8,10 15,14 22,4" stroke="#386ad9" stroke-width="2" fill="none"/><circle cx="2" cy="17" r="2" fill="#386ad9"/><circle cx="8" cy="10" r="2" fill="#386ad9"/><circle cx="15" cy="14" r="2" fill="#386ad9"/><circle cx="22" cy="4" r="2" fill="#386ad9"/></svg>
                        </button>
                        <button type="button" id="chart-area" class="ifm-charttype-btn" title="Flächendiagramm" style="padding:3px 12px;">
                            <svg width="24" height="20"><polyline points="2,17 8,10 15,14 22,4" stroke="#2cb179" stroke-width="2" fill="none"/><polygon points="2,17 8,10 15,14 22,4 22,17 2,17" fill="#2cb17933"/><circle cx="2" cy="17" r="2" fill="#2cb179"/><circle cx="8" cy="10" r="2" fill="#2cb179"/><circle cx="15" cy="14" r="2" fill="#2cb179"/><circle cx="22" cy="4" r="2" fill="#2cb179"/></svg>
                        </button>
                        <button type="button" id="chart-bar" class="ifm-charttype-btn" title="Balkendiagramm" style="padding:3px 12px;">
                            <svg width="24" height="20"><rect x="2" y="13" width="4" height="5" fill="#e2a13a"/><rect x="9" y="6" width="4" height="12" fill="#e2a13a"/><rect x="16" y="2" width="4" height="16" fill="#e2a13a"/></svg>
                        </button>
                        <button type="button" id="toggle-online" class="ifm-toggle-btn" style="background:#cfe2ff; margin-left:14px;">Online-Umsatz</button>
                        <button type="button" id="toggle-offline" class="ifm-toggle-btn" style="background:#ffd6d6;">Offline-Umsatz</button>
                        <button type="button" id="toggle-total" class="ifm-toggle-btn" style="background:#d1ffd7;">Gesamtumsatz</button>
                        <label style="margin-left:16px; font-size:0.99em;">
                            <input type="checkbox" id="toggle-cumulative"> Kumuliert
                        </label>
                    </div>
                    <div style="display: flex; justify-content: center;">
                        <canvas id="salesChart" width="670" height="280"></canvas>
                    </div>
                </div>
                <!-- PieChart -->
                <div class="ifm-pie-wrap">
                    <div style="font-weight:500; margin-bottom:8px;">Umsatzanteil Online vs. Offline</div>
                    <canvas id="salesPie" width="140" height="140"></canvas>
                    <div class="ifm-pie-legend">
                        <span style="display:flex; align-items:center;"><span style="display:inline-block;width:16px;height:10px;background:#3399ff; border-radius:3px; margin-right:6px;"></span>Online-Umsatz</span>
                        <span style="display:flex; align-items:center;"><span style="display:inline-block;width:16px;height:10px;background:#ff5d5d; border-radius:3px; margin-right:6px;"></span>Offline-Umsatz</span>
                    </div>
                </div>
            </div>
            <!-- Forecast-Abschnitt GANZ UNTEN -->
            <h1 style="margin-bottom:0.4em; margin-top:36px;"> Forecast</h1>
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:stretch;">
                <div style="flex:1; min-width:220px; background:#e8f3fe; border-radius:14px; box-shadow:0 1px 4px #ddd; padding:22px 18px 12px 18px; text-align:center;">
                    <div style="font-size:1.5em; font-weight:bold; margin-bottom:0.5em;">
                        <?= number_format($forecast_revenue, 2, ',', '.') ?> €
                    </div>
                    <div style="font-size:1.09em; color:#1859a6;">Prognose Umsatz<br>nächste <?= $forecast_days ?> Tage</div>
                </div>
                <div style="flex:1; min-width:220px; background:#f5ffe3; border-radius:14px; box-shadow:0 1px 4px #ddd; padding:22px 18px 12px 18px; text-align:center;">
                    <div style="font-size:1.5em; font-weight:bold; margin-bottom:0.5em;">
                        <?= intval($forecast_orders) ?>
                    </div>
                    <div style="font-size:1.09em; color:#708100;">Prognose Verkäufe<br>nächste <?= $forecast_days ?> Tage</div>
                </div>
            </div>
            <style>
                .ifm-toggle-btn {
                    border-radius: 8px;
                    border: 1.1px solid #bbb;
                    font-size: 1em;
                    padding: 3px 13px;
                    margin-right: 3px;
                    cursor: pointer;
                    transition: opacity 0.18s, box-shadow 0.15s, background 0.13s;
                    opacity: 1;
                    background: #f9f9f9;
                }
                .ifm-toggle-btn.off { opacity: 0.5; }
                .ifm-toggle-btn:active { box-shadow: 0 0 2px #888; }
                .ifm-charttype-btn {
                    border-radius: 8px;
                    border: 1.1px solid #bbb;
                    background: #f4f4f4;
                    margin-right: 2px;
                    cursor: pointer;
                    opacity: 0.88;
                    transition: opacity 0.16s, border 0.13s, background 0.16s;
                }
                .ifm-charttype-btn.selected {
                    border: 2px solid #226ed8;
                    background: #e8f0ff;
                    opacity: 1;
                }
                .ifm-charttype-btn:active { box-shadow: 0 0 2px #aaa; }
            </style>
            <script>
            document.getElementById('range').addEventListener('change', function() {
                document.getElementById('custom-date-fields').style.display = (this.value === 'custom') ? 'inline' : 'none';
            });
            document.addEventListener('DOMContentLoaded', function() {
                // Pie Chart
                const pieCtx = document.getElementById('salesPie').getContext('2d');
                new Chart(pieCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Online-Umsatz', 'Offline-Umsatz'],
                        datasets: [{
                            data: [<?= $online_sales['revenue'] ?>, <?= $offline_sales['revenue'] ?>],
                            backgroundColor: ['#3399ff', '#ff5d5d'],
                            borderWidth: 1,
                        }]
                    },
                    options: { plugins: { legend: { display: false } } }
                });
                // Umsatzentwicklung Chart (zentriert & Typ-Umschaltung gefixt)
                const ctx = document.getElementById('salesChart').getContext('2d');
                const dates = <?= wp_json_encode($dates); ?>;
                const online = <?= wp_json_encode($online_sales_data); ?>;
                const offline = <?= wp_json_encode($offline_sales_data); ?>;
                const total = <?= wp_json_encode($total_sales_data); ?>;
                const online_cum = <?= wp_json_encode($online_cum); ?>;
                const offline_cum = <?= wp_json_encode($offline_cum); ?>;
                const total_cum = <?= wp_json_encode($total_cum); ?>;
                let isCumulative = false;
                let chartType = 'line';
                let isArea = false;

                let showOnline = true, showOffline = true, showTotal = true;
                let salesChart = makeChart();

                function makeChart() {
                    let datasets = [
                        {
                            label: 'Online-Umsatz',
                            data: isCumulative ? online_cum : online,
                            borderColor: 'blue',
                            backgroundColor: 'rgba(80,140,255,0.19)',
                            fill: isArea,
                            tension: 0.17,
                            pointRadius: chartType === 'bar' ? 0 : 2
                        },
                        {
                            label: 'Offline-Umsatz',
                            data: isCumulative ? offline_cum : offline,
                            borderColor: 'red',
                            backgroundColor: 'rgba(255,80,80,0.19)',
                            fill: isArea,
                            tension: 0.17,
                            pointRadius: chartType === 'bar' ? 0 : 2
                        },
                        {
                            label: 'Gesamtumsatz',
                            data: isCumulative ? total_cum : total,
                            borderColor: 'green',
                            backgroundColor: 'rgba(66,200,84,0.19)',
                            fill: isArea,
                            tension: 0.17,
                            pointRadius: chartType === 'bar' ? 0 : 2
                        }
                    ];
                    // Für Balken keine Flächenfüllung
                    if (chartType === 'bar') {
                        datasets.forEach(ds => ds.fill = false);
                    }
                    return new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: dates,
                            datasets: datasets
                        },
                        options: {
                            responsive: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { display: true, title: { display: true, text: 'Datum' } },
                                y: { display: true, title: { display: true, text: 'Umsatz (€)' }, beginAtZero: true }
                            }
                        }
                    });
                }

                function updateVisibility() {
                    salesChart.data.datasets[0].hidden = !showOnline;
                    salesChart.data.datasets[1].hidden = !showOffline;
                    salesChart.data.datasets[2].hidden = !showTotal;
                    salesChart.update();
                }
                document.getElementById('toggle-online').addEventListener('click', function() {
                    showOnline = !showOnline;
                    this.classList.toggle('off', !showOnline);
                    updateVisibility();
                });
                document.getElementById('toggle-offline').addEventListener('click', function() {
                    showOffline = !showOffline;
                    this.classList.toggle('off', !showOffline);
                    updateVisibility();
                });
                document.getElementById('toggle-total').addEventListener('click', function() {
                    showTotal = !showTotal;
                    this.classList.toggle('off', !showTotal);
                    updateVisibility();
                });
                document.getElementById('toggle-cumulative').addEventListener('change', function() {
                    isCumulative = this.checked;
                    salesChart.data.datasets[0].data = isCumulative ? online_cum : online;
                    salesChart.data.datasets[1].data = isCumulative ? offline_cum : offline;
                    salesChart.data.datasets[2].data = isCumulative ? total_cum : total;
                    salesChart.update();
                });

                document.getElementById('chart-line').addEventListener('click', function() {
                    chartType = 'line';
                    isArea = false;
                    setChartTypeBtn(this);
                    salesChart.destroy();
                    salesChart = makeChart();
                    updateVisibility();
                });
                document.getElementById('chart-area').addEventListener('click', function() {
                    chartType = 'line';
                    isArea = true;
                    setChartTypeBtn(this);
                    salesChart.destroy();
                    salesChart = makeChart();
                    updateVisibility();
                });
                document.getElementById('chart-bar').addEventListener('click', function() {
                    chartType = 'bar';
                    isArea = false;
                    setChartTypeBtn(this);
                    salesChart.destroy();
                    salesChart = makeChart();
                    updateVisibility();
                });
                function setChartTypeBtn(el) {
                    document.querySelectorAll('.ifm-charttype-btn').forEach(b => b.classList.remove('selected'));
                    el.classList.add('selected');
                }
            });
            </script>
        </div>
        <?php
    }

    // Umsatz & Orders ONLINE
    public static function get_wc_sales($from, $to) {
        $args = [
            'status' => ['wc-completed'],
            'limit' => -1,
            'date_created' => $from . '...' . $to,
        ];
        $orders = wc_get_orders($args);
        $revenue = 0;
        foreach ($orders as $order) $revenue += (float) $order->get_total();
        return ['revenue' => $revenue, 'count' => count($orders)];
    }

    // Umsatz & Orders OFFLINE
    public static function get_offline_sales($from, $to) {
        global $wpdb;
        $table = $wpdb->prefix . 'ifm_offline_sales';
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT SUM(sale_price) as total, COUNT(*) as count
            FROM $table
            WHERE sale_date BETWEEN %s AND %s
        ", $from, $to));
        return [
            'revenue' => (float) ($rows[0]->total ?? 0),
            'count' => (int) ($rows[0]->count ?? 0),
        ];
    }

    // Umsatz eines Tages ONLINE
    public static function get_online_sales_for_day($date) {
        $args = [
            'status' => ['wc-completed'],
            'limit' => -1,
            'date_created' => $date,
        ];
        $orders = wc_get_orders($args);
        $revenue = 0;
        foreach ($orders as $order) $revenue += (float) $order->get_total();
        return round($revenue, 2);
    }

    // Umsatz eines Tages OFFLINE
    public static function get_offline_sales_for_day($date) {
        global $wpdb;
        $table = $wpdb->prefix . 'ifm_offline_sales';
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT SUM(sale_price) as total
            FROM $table
            WHERE sale_date = %s
        ", $date));
        return round((float) ($rows[0]->total ?? 0), 2);
    }

    public static function get_wc_total_items($from, $to) {
        $args = [
            'status' => ['wc-completed'],
            'limit' => -1,
            'date_created' => $from . '...' . $to,
        ];
        $orders = wc_get_orders($args);
        $items = 0;
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) $items += $item->get_quantity();
        }
        return $items;
    }

    public static function get_offline_total_items($from, $to) {
        global $wpdb;
        $table = $wpdb->prefix . 'ifm_offline_sales';
        $row = $wpdb->get_row($wpdb->prepare("
            SELECT SUM(quantity) as sum FROM $table WHERE sale_date BETWEEN %s AND %s
        ", $from, $to));
        return (int) ($row->sum ?? 0);
    }

    public static function get_bestseller($from, $to) {
        // Online Bestseller
        $args = [
            'status' => ['wc-completed'],
            'limit' => -1,
            'date_created' => $from . '...' . $to,
        ];
        $orders = wc_get_orders($args);
        $product_counts = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                $product_counts[$pid] = ($product_counts[$pid] ?? 0) + $item->get_quantity();
            }
        }
        // Offline Bestseller
        global $wpdb;
        $table = $wpdb->prefix . 'ifm_offline_sales';
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT product_id, SUM(quantity) as qty FROM $table WHERE sale_date BETWEEN %s AND %s GROUP BY product_id
        ", $from, $to));
        foreach ($results as $row) {
            $product_counts[$row->product_id] = ($product_counts[$row->product_id] ?? 0) + $row->qty;
        }
        if (empty($product_counts)) return "–";
        arsort($product_counts);
        $best_id = array_key_first($product_counts);
        $product = wc_get_product($best_id);
        return $product ? $product->get_name() : "Produkt #" . $best_id;
    }
}
?>
