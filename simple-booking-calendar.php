<?php
/**
 * Plugin Name: Simple Booking Calendar
 * Plugin URI: https://colect.app
 * Description: Sistema simple de reservas con calendario
 * Version: 1.0.0
 * Author: Efrain Morales
 * Author URI: https://colect.app
 * License: GPL v2 or later
 * Text Domain: simple-booking-calendar
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('SBC_VERSION', '1.0.0');
define('SBC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBC_PLUGIN_URL', plugin_dir_url(__FILE__));

class SimpleBookingCalendar {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'simple_bookings';

        // Hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Hooks de WordPress
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_sbc_save_booking', array($this, 'save_booking'));
        add_action('wp_ajax_nopriv_sbc_save_booking', array($this, 'save_booking'));
        add_action('wp_ajax_sbc_get_bookings', array($this, 'get_bookings'));
        add_action('wp_ajax_nopriv_sbc_get_bookings', array($this, 'get_bookings'));

        // Shortcode
        add_shortcode('booking_calendar', array($this, 'render_calendar'));
    }

    // Crear tabla en la base de datos
    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_date date NOT NULL,
            booking_time varchar(10) NOT NULL,
            name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY booking_date (booking_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('sbc_version', SBC_VERSION);
    }

    public function deactivate() {
        // Opcional: descomentar para eliminar tabla al desactivar
        // global $wpdb;
        // $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }

    // Menú de administración
    public function add_admin_menu() {
        add_menu_page(
            'Reservas',
            'Reservas',
            'manage_options',
            'simple-booking-calendar',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
    }

    // Página de administración
    public function admin_page() {
        global $wpdb;

        // Eliminar reserva
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $wpdb->delete($this->table_name, array('id' => $id));
            echo '<div class="notice notice-success"><p>Reserva eliminada correctamente.</p></div>';
        }

        // Obtener todas las reservas
        $bookings = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY booking_date DESC, booking_time DESC");

        ?>
        <div class="wrap">
            <h1>📅 Gestión de Reservas</h1>

            <h2>Shortcode para usar en páginas:</h2>
            <code>[booking_calendar]</code>

            <h2>Reservas Registradas (<?php echo count($bookings); ?>)</h2>

            <?php if (empty($bookings)): ?>
                <p>No hay reservas registradas aún.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo esc_html($booking->id); ?></td>
                                <td><?php echo esc_html(date('d/m/Y', strtotime($booking->booking_date))); ?></td>
                                <td><?php echo esc_html($booking->booking_time); ?></td>
                                <td><?php echo esc_html($booking->name); ?></td>
                                <td><?php echo esc_html($booking->phone); ?></td>
                                <td><?php echo esc_html($booking->email); ?></td>
                                <td><?php echo esc_html($booking->company); ?></td>
                                <td><?php echo esc_html($booking->status); ?></td>
                                <td>
                                    <a href="?page=simple-booking-calendar&action=delete&id=<?php echo $booking->id; ?>" 
                                       onclick="return confirm('¿Eliminar esta reserva?');"
                                       class="button button-small">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // Cargar scripts frontend
    public function enqueue_scripts() {
        wp_enqueue_style('sbc-styles', SBC_PLUGIN_URL . 'assets/style.css', array(), SBC_VERSION);
        wp_enqueue_script('sbc-script', SBC_PLUGIN_URL . 'assets/script.js', array('jquery'), SBC_VERSION, true);

        wp_localize_script('sbc-script', 'sbcAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sbc_nonce')
        ));
    }

    // Cargar scripts admin
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_simple-booking-calendar') {
            return;
        }
        wp_enqueue_style('sbc-admin-styles', SBC_PLUGIN_URL . 'assets/admin-style.css', array(), SBC_VERSION);
    }

    // Guardar reserva (AJAX)
    public function save_booking() {
        check_ajax_referer('sbc_nonce', 'nonce');

        global $wpdb;

        $booking_date = sanitize_text_field($_POST['booking_date']);
        $booking_time = sanitize_text_field($_POST['booking_time']);
        $name = sanitize_text_field($_POST['name']);
        $phone = sanitize_text_field($_POST['phone']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);

        // Validaciones
        if (empty($booking_date) || empty($booking_time) || empty($name) || empty($phone) || empty($email)) {
            wp_send_json_error(array('message' => 'Todos los campos son obligatorios excepto empresa.'));
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Email inválido.'));
            return;
        }

        // Verificar si ya existe una reserva para esa fecha y hora
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE booking_date = %s AND booking_time = %s",
            $booking_date,
            $booking_time
        ));

        if ($existing > 0) {
            wp_send_json_error(array('message' => 'Esta fecha y hora ya están reservadas.'));
            return;
        }

        // Insertar reserva
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'booking_date' => $booking_date,
                'booking_time' => $booking_time,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'company' => $company,
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            wp_send_json_success(array('message' => '¡Reserva realizada con éxito!'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar la reserva.'));
        }
    }

    // Obtener reservas (AJAX)
    public function get_bookings() {
        check_ajax_referer('sbc_nonce', 'nonce');

        global $wpdb;

        $date = sanitize_text_field($_POST['date']);

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_time FROM {$this->table_name} WHERE booking_date = %s",
            $date
        ));

        $booked_times = array();
        foreach ($bookings as $booking) {
            $booked_times[] = $booking->booking_time;
        }

        wp_send_json_success(array('booked_times' => $booked_times));
    }

    // Renderizar calendario (Shortcode)
    public function render_calendar() {
        ob_start();
        ?>
        <div class="sbc-calendar-container">
            <h2>📅 Sistema de Reservas</h2>

            <form id="sbc-booking-form" class="sbc-form">
                <div class="sbc-form-group">
                    <label for="sbc-date">Fecha de Reserva *</label>
                    <input type="date" id="sbc-date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="sbc-form-group">
                    <label for="sbc-time">Hora *</label>
                    <select id="sbc-time" name="booking_time" required>
                        <option value="">Seleccione una hora</option>
                        <?php
                        for ($h = 15; $h <= 17; $h++) {
                            echo "<option value='{$h}:00'>{$h}:00</option>";
                            if ($h < 18) {
                                echo "<option value='{$h}:30'>{$h}:30</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="sbc-form-group">
                    <label for="sbc-name">Nombre Completo *</label>
                    <input type="text" id="sbc-name" name="name" required>
                </div>

                <div class="sbc-form-group">
                    <label for="sbc-phone">Teléfono *</label>
                    <input type="tel" id="sbc-phone" name="phone" required>
                </div>

                <div class="sbc-form-group">
                    <label for="sbc-email">Email *</label>
                    <input type="email" id="sbc-email" name="email" required>
                </div>

                <div class="sbc-form-group">
                    <label for="sbc-company">Empresa</label>
                    <input type="text" id="sbc-company" name="company">
                </div>

                <div id="sbc-message" class="sbc-message"></div>

                <button type="submit" class="sbc-submit-btn">Reservar</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializar el plugin
new SimpleBookingCalendar();