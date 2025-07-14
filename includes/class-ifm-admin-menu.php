<?php
class IFM_Admin_Menu {

    public static function init() {
        // Hauptmenüpunkt
        add_menu_page(
            'Inventory Forecast Manager',
            'Inventory Manager',
            'manage_options',
            'ifm-dashboard',
            [self::class, 'render_dashboard'],
            'dashicons-chart-line',
            56
        );
        // Untermenü: Offline-Verkäufe
        add_submenu_page(
            'ifm-dashboard',
            'Offline-Verkäufe',
            'Offline-Verkäufe',
            'manage_options',
            'ifm-offline-sales',
            [self::class, 'render_offline_sales']
        );
        // Untermenü: Forecast & KPIs
        add_submenu_page(
            'ifm-dashboard',
            'Forecast & KPIs',
            'Forecast & KPIs',
            'manage_options',
            'ifm-forecast-kpis',
            [self::class, 'render_forecast']
        );
        // Untermenü: Stock Central
        add_submenu_page(
            'ifm-dashboard',
            'Stock Central',
            'Stock Central',
            'manage_options',
            'ifm-stock-central',
            [self::class, 'render_stock_central']
        );
        // Untermenü: PDF Export
        add_submenu_page(
            'ifm-dashboard',
            'PDF Export',
            'PDF Export',
            'manage_options',
            'ifm-pdf-export',
            [IFM_PDF_Export::class, 'render_pdf_export_page']
        );
    }

    // Dashboard (Startseite des Plugins)
    public static function render_dashboard() {
        ?>
        <div class="wrap">
            <h1>Inventory Forecast Manager</h1>
            <p>Willkommen im Inventory Manager Dashboard!</p>
            <div class="ifm-dashboard-buttons">
                <a href="?page=ifm-offline-sales" class="ifm-btn">Offline-Verkäufe erfassen</a>
                <a href="?page=ifm-forecast-kpis" class="ifm-btn">Forecast & KPIs</a>
                <a href="?page=ifm-stock-central" class="ifm-btn">Stock Central</a>
                <a href="?page=ifm-pdf-export" class="ifm-btn">PDF Export</a>
            </div>
        </div>
        <style>
            .ifm-dashboard-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
            }
            .ifm-btn {
                flex: 1 1 200px;
                text-align: center;
                padding: 18px 25px;
                background-color: #2c7be5;
                color: #fff;
                font-weight: 600;
                font-size: 1.15em;
                border-radius: 10px;
                text-decoration: none;
                box-shadow: 0 4px 8px rgb(44 123 229 / 0.4);
                transition: background-color 0.3s ease, box-shadow 0.3s ease;
            }
            .ifm-btn:hover,
            .ifm-btn:focus {
                background-color: #1a5cc8;
                box-shadow: 0 6px 12px rgb(26 92 200 / 0.6);
                color: #fff;
                outline: none;
            }
            @media (max-width: 600px) {
                .ifm-btn {
                    flex: 1 1 100%;
                }
            }
        </style>
        <?php
    }

    public static function render_offline_sales() {
        IFM_Offline_Sales::render_form();
    }

    public static function render_forecast() {
        IFM_Forecast::render_forecast();
    }

    public static function render_stock_central() {
        IFM_Stock_Central::render_stock_central();
    }
}
