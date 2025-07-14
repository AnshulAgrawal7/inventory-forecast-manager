<?php
class IFM_PDF_Export {

    public static function init() {
        add_action('ifm_pdf_export_dropdown', [self::class, 'render_export_dropdown']);
        add_action('admin_post_ifm_export_pdf', [self::class, 'handle_pdf_export']);
    }

    // Die Seite für den Menü-Tab "PDF Export"
    public static function render_pdf_export_page() {
        echo '<div class="wrap"><h1>PDF Export</h1>';
        do_action('ifm_pdf_export_dropdown');
        echo '</div>';
    }

    // Das Dropdown-Formular
    public static function render_export_dropdown() {
        ?>
        <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" target="_blank" id="ifm-pdf-export-form">
            <input type="hidden" name="action" value="ifm_export_pdf">
            <label for="ifm_export_type">Was soll als PDF exportiert werden?&nbsp;</label>
            <select name="export_type" id="ifm_export_type" style="min-width:180px;">
                <option value="kpi">KPIs & Forecast</option>
                <option value="stock">Stock Central</option>
            </select>
            <input type="hidden" name="sales_chart_img" id="sales_chart_img">
            <button type="submit" class="button button-primary" style="margin-left:14px;">PDF exportieren</button>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            let chartCanvas = document.getElementById('salesChart');
            let imgInput = document.getElementById('sales_chart_img');
            if (chartCanvas && imgInput) {
                try {
                    imgInput.value = chartCanvas.toDataURL('image/png');
                } catch(e){}
            }
        });
        </script>
        <?php
    }

    public static function handle_pdf_export() {
        $type = $_POST['export_type'] ?? '';
        if ($type === 'kpi') {
            include __DIR__ . '/pdf-template-kpi.php';
        } elseif ($type === 'stock') {
            include __DIR__ . '/pdf-template-stock.php';
        } else {
            echo 'Ungültiger Export-Typ!';
        }
        exit;
    }
}
