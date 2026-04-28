<?php
/**
 * Plugin Name: SRK Pics Quote Pro
 * Plugin URI: https://srkpics.com/
 * Description: WooCommerce quote popup system with hidden prices, product-aware quote requests, HTML emails, CRM-style admin dashboard, filters, CSV export, status management, and live activity logs.
 * Version: 1.3.0
 * Author: srkpics
 * Author URI: https://srkpics.com/
 * Text Domain: srkpics-quote-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SRKPICS_Quote_Pro {
    const VERSION = '1.3.0';
    const DB_VERSION = '1.0.0';
    const OPT_ADMIN_EMAIL = 'srkqp_admin_email';
    const OPT_DB_VERSION = 'srkqp_db_version';

    private static $instance = null;
    private $table_quotes;
    private $table_logs;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_quotes = $wpdb->prefix . 'srkqp_quotes';
        $this->table_logs = $wpdb->prefix . 'srkqp_logs';

        register_activation_hook(__FILE__, array($this, 'activate'));

        add_action('plugins_loaded', array($this, 'maybe_upgrade'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

        add_action('init', array($this, 'handle_export'));

        add_filter('woocommerce_get_price_html', array($this, 'hide_price'), 999, 2);
        add_filter('woocommerce_variable_price_html', array($this, 'hide_price'), 999, 2);
        add_filter('woocommerce_variation_price_html', array($this, 'hide_price'), 999, 2);
        add_filter('woocommerce_is_purchasable', '__return_false', 999);
        add_filter('woocommerce_variation_is_purchasable', '__return_false', 999);
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'replace_loop_cart_button'), 999, 3);

        add_action('woocommerce_after_shop_loop_item', array($this, 'loop_quote_button'), 20);
        add_action('wp_footer', array($this, 'popup_markup'), 20);

        add_action('wp_ajax_srkqp_submit_quote', array($this, 'ajax_submit_quote'));
        add_action('wp_ajax_nopriv_srkqp_submit_quote', array($this, 'ajax_submit_quote'));

        add_action('wp_ajax_srkqp_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_srkqp_get_logs', array($this, 'ajax_get_logs'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_srkqp_save_settings', array($this, 'save_settings'));
        add_shortcode('srk_quote_button', array($this, 'quote_shortcode'));
    }

    public function activate() {
        $this->create_tables();
        if (!get_option(self::OPT_ADMIN_EMAIL)) {
            update_option(self::OPT_ADMIN_EMAIL, get_option('admin_email'));
        }
        update_option(self::OPT_DB_VERSION, self::DB_VERSION);
        $this->log_activity('Plugin activated', 'system');
    }

    public function maybe_upgrade() {
        if (get_option(self::OPT_DB_VERSION) !== self::DB_VERSION) {
            $this->create_tables();
            update_option(self::OPT_DB_VERSION, self::DB_VERSION);
        }
    }

    private function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $sql_quotes = "CREATE TABLE {$this->table_quotes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED DEFAULT 0,
            product_name TEXT NOT NULL,
            product_image TEXT NULL,
            product_url TEXT NULL,
            customer_name VARCHAR(190) NOT NULL,
            customer_email VARCHAR(190) NOT NULL,
            customer_phone VARCHAR(80) NOT NULL,
            customer_message LONGTEXT NULL,
            status VARCHAR(60) NOT NULL DEFAULT 'new',
            ip_address VARCHAR(80) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset;";

        $sql_logs = "CREATE TABLE {$this->table_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT UNSIGNED DEFAULT 0,
            message TEXT NOT NULL,
            log_type VARCHAR(60) NOT NULL DEFAULT 'info',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY quote_id (quote_id),
            KEY created_at (created_at)
        ) $charset;";

        dbDelta($sql_quotes);
        dbDelta($sql_logs);
    }

    public function hide_price($price, $product = null) {
        return '';
    }

    public function replace_loop_cart_button($html, $product, $args) {
        return '';
    }

    private function get_statuses() {
        return array(
            'new' => 'New',
            'waiting' => 'Waiting for Confirmation',
            'call_done' => 'Call Done',
            'quoted' => 'Quote Sent',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
        );
    }

    private function product_payload($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return array();
        }

        $image = get_the_post_thumbnail_url($product->get_id(), 'medium');
        if (!$image) {
            $image = wc_placeholder_img_src('medium');
        }

        return array(
            'id' => absint($product->get_id()),
            'name' => $product->get_name(),
            'image' => $image,
            'url' => get_permalink($product->get_id()),
        );
    }

    private function quote_button_html($product, $extra_class = '') {
        $data = $this->product_payload($product);
        if (empty($data)) {
            return '';
        }

        return sprintf(
            '<button type="button" class="srkqp-quote-btn %1$s" data-product-id="%2$d" data-product-name="%3$s" data-product-image="%4$s" data-product-url="%5$s"><span>Request Quote</span></button>',
            esc_attr($extra_class),
            esc_attr($data['id']),
            esc_attr($data['name']),
            esc_url($data['image']),
            esc_url($data['url'])
        );
    }

    public function loop_quote_button() {
        global $product;
        echo $this->quote_button_html($product, 'srkqp-loop-btn'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function single_quote_button() {
        global $product;
        static $printed = false;
        if ($printed || !is_product()) {
            return;
        }
        $printed = true;
        echo '<div class="srkqp-single-button-wrap">' . $this->quote_button_html($product, 'srkqp-single-btn') . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function single_product_fallback_button() {
        if (!is_product()) {
            return;
        }
        global $product;
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        $btn = $this->quote_button_html($product, 'srkqp-single-btn srkqp-fallback-btn');
        if (!$btn) {
            return;
        }
        ?>
        <div id="srkqp-single-fallback-template" style="display:none;"><?php echo $btn; ?></div>
        <?php
    }

    public function quote_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'product_id' => 0,
            'text' => 'Request Quote',
            'class' => '',
        ), $atts, 'srk_quote_button');

        $product_id = absint($atts['product_id']);
        if (!$product_id && is_product()) {
            $product_id = get_the_ID();
        }

        $product = $product_id ? wc_get_product($product_id) : null;
        if (!$product) {
            return '';
        }

        $button = $this->quote_button_html($product, 'srkqp-single-btn ' . sanitize_html_class($atts['class']));
        if ($atts['text'] !== 'Request Quote') {
            $button = str_replace('<span>Request Quote</span>', '<span>' . esc_html($atts['text']) . '</span>', $button);
        }

        return $button;
    }

    public function frontend_assets() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        wp_enqueue_style('srkqp-frontend', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', array(), self::VERSION);
        wp_enqueue_script('srkqp-frontend', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', array('jquery'), self::VERSION, true);

        $single_product_payload = array();
        if (is_product()) {
            global $product;
            if (!$product || !is_a($product, 'WC_Product')) {
                $product = wc_get_product(get_the_ID());
            }
            $single_product_payload = $this->product_payload($product);
        }

        wp_localize_script('srkqp-frontend', 'SRKQP', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srkqp_quote_nonce'),
            'is_single_product' => is_product() ? 1 : 0,
            'single_product' => $single_product_payload,
        ));
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'srkqp') === false && strpos($hook, 'quote-pro') === false) {
            return;
        }

        wp_enqueue_style('srkqp-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), self::VERSION);
        wp_enqueue_script('srkqp-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), self::VERSION, true);

        wp_localize_script('srkqp-admin', 'SRKQP_ADMIN', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srkqp_admin_nonce'),
        ));
    }

    public function popup_markup() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        ?>
        <div id="srkqp-popup" class="srkqp-popup" aria-hidden="true">
            <div class="srkqp-overlay" data-srkqp-close></div>
            <div class="srkqp-modal" role="dialog" aria-modal="true" aria-labelledby="srkqp-title">
                <button type="button" class="srkqp-close" data-srkqp-close aria-label="Close quote form">&times;</button>

                <div class="srkqp-product-card">
                    <img id="srkqp-product-image" src="" alt="">
                    <div>
                        <p class="srkqp-eyebrow">Selected Product</p>
                        <h3 id="srkqp-product-name">Product Name</h3>
                        <a id="srkqp-product-link" href="#" target="_blank" rel="noopener">View product</a>
                    </div>
                </div>

                <h2 id="srkqp-title">Request a Quote</h2>
                <p class="srkqp-subtitle">Share your details below. Our team will contact you with the next steps.</p>

                <form id="srkqp-form">
                    <input type="hidden" name="action" value="srkqp_submit_quote">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('srkqp_quote_nonce')); ?>">
                    <input type="hidden" name="product_id" id="srkqp-product-id">
                    <input type="hidden" name="product_name" id="srkqp-product-name-field">
                    <input type="hidden" name="product_image" id="srkqp-product-image-field">
                    <input type="hidden" name="product_url" id="srkqp-product-url-field">

                    <label>Name <input type="text" name="customer_name" required></label>
                    <label>Email <input type="email" name="customer_email" required></label>
                    <label>Phone <input type="text" name="customer_phone" required></label>
                    <label>Message <textarea name="customer_message" rows="4" placeholder="Tell us what you need..."></textarea></label>

                    <button type="submit" class="srkqp-submit">Submit Quote Request</button>
                    <div class="srkqp-response" aria-live="polite"></div>
                </form>
            </div>
        </div>
        <?php
    }

    public function ajax_submit_quote() {
        check_ajax_referer('srkqp_quote_nonce', 'nonce');

        global $wpdb;

        $product_id = absint($_POST['product_id'] ?? 0);
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $product_image = esc_url_raw($_POST['product_image'] ?? '');
        $product_url = esc_url_raw($_POST['product_url'] ?? '');
        $name = sanitize_text_field($_POST['customer_name'] ?? '');
        $email = sanitize_email($_POST['customer_email'] ?? '');
        $phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $message = sanitize_textarea_field($_POST['customer_message'] ?? '');

        if (!$name || !$email || !$phone || !$product_name || !is_email($email)) {
            wp_send_json_error(array('message' => 'Please complete all required fields.'));
        }

        $now = current_time('mysql');

        $inserted = $wpdb->insert($this->table_quotes, array(
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_image' => $product_image,
            'product_url' => $product_url,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'customer_message' => $message,
            'status' => 'new',
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if (!$inserted) {
            wp_send_json_error(array('message' => 'Unable to submit the quote request. Please try again.'));
        }

        $quote_id = (int) $wpdb->insert_id;
        $this->log_activity('New quote request submitted for ' . $product_name, 'quote', $quote_id);

        $this->send_emails($quote_id);

        wp_send_json_success(array(
            'message' => 'Thank you. Your quote request has been submitted. We will contact you soon.',
            'quote_id' => $quote_id,
        ));
    }

    private function send_emails($quote_id) {
        global $wpdb;
        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_quotes} WHERE id = %d", $quote_id));
        if (!$quote) return;

        $admin_email = sanitize_email(get_option(self::OPT_ADMIN_EMAIL, get_option('admin_email')));
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $admin_subject = 'New Quote Request: ' . wp_strip_all_tags($quote->product_name);
        $customer_subject = 'We received your quote request';

        wp_mail($admin_email, $admin_subject, $this->email_template($quote, 'admin'), $headers);
        wp_mail($quote->customer_email, $customer_subject, $this->email_template($quote, 'customer'), $headers);

        $this->log_activity('Admin and customer email notifications sent', 'email', $quote_id);
    }

    private function email_template($quote, $type = 'admin') {
        $title = $type === 'admin' ? 'New Quote Request' : 'Quote Request Received';
        $intro = $type === 'admin'
            ? 'A customer submitted a new quote request. Details are below.'
            : 'Thank you for your request. We received your quote details and will contact you soon.';

        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;background:#f6f6f6;padding:24px;">
            <div style="max-width:680px;margin:auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e7e7e7;">
                <div style="background:#111827;color:#fff;padding:22px 26px;">
                    <h2 style="margin:0;font-size:24px;"><?php echo esc_html($title); ?></h2>
                    <p style="margin:8px 0 0;color:#d1d5db;"><?php echo esc_html($intro); ?></p>
                </div>
                <div style="padding:24px 26px;">
                    <?php if (!empty($quote->product_image)) : ?>
                        <img src="<?php echo esc_url($quote->product_image); ?>" alt="" style="max-width:160px;border-radius:10px;margin-bottom:16px;">
                    <?php endif; ?>

                    <h3 style="margin:0 0 8px;color:#111827;"><?php echo esc_html($quote->product_name); ?></h3>
                    <?php if (!empty($quote->product_url)) : ?>
                        <p><a href="<?php echo esc_url($quote->product_url); ?>" style="color:#b7791f;">View Product</a></p>
                    <?php endif; ?>

                    <table style="width:100%;border-collapse:collapse;margin-top:18px;">
                        <tr><td style="padding:10px;border-bottom:1px solid #eee;font-weight:bold;">Name</td><td style="padding:10px;border-bottom:1px solid #eee;"><?php echo esc_html($quote->customer_name); ?></td></tr>
                        <tr><td style="padding:10px;border-bottom:1px solid #eee;font-weight:bold;">Email</td><td style="padding:10px;border-bottom:1px solid #eee;"><?php echo esc_html($quote->customer_email); ?></td></tr>
                        <tr><td style="padding:10px;border-bottom:1px solid #eee;font-weight:bold;">Phone</td><td style="padding:10px;border-bottom:1px solid #eee;"><?php echo esc_html($quote->customer_phone); ?></td></tr>
                        <tr><td style="padding:10px;border-bottom:1px solid #eee;font-weight:bold;">Message</td><td style="padding:10px;border-bottom:1px solid #eee;"><?php echo nl2br(esc_html($quote->customer_message)); ?></td></tr>
                    </table>

                    <?php if ($type !== 'admin') : ?>
                        <p style="margin-top:22px;color:#444;">We will review your request and contact you with quote details shortly.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function admin_menu() {
        add_menu_page('Quote Pro', 'Quote Pro', 'manage_woocommerce', 'srkqp-quotes', array($this, 'quotes_page'), 'dashicons-format-status', 56);
        add_submenu_page('srkqp-quotes', 'Quote Requests', 'Quote Requests', 'manage_woocommerce', 'srkqp-quotes', array($this, 'quotes_page'));
        add_submenu_page('srkqp-quotes', 'Settings', 'Settings', 'manage_options', 'srkqp-settings', array($this, 'settings_page'));
        add_submenu_page('srkqp-quotes', 'Activity Log', 'Activity Log', 'manage_woocommerce', 'srkqp-logs', array($this, 'logs_page'));
    }

    public function quotes_page() {
        global $wpdb;

        $statuses = $this->get_statuses();
        $status = sanitize_text_field($_GET['status'] ?? '');
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['date_to'] ?? '');

        $where = 'WHERE 1=1';
        $args = array();

        if ($status && isset($statuses[$status])) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }
        if ($date_from) {
            $where .= ' AND created_at >= %s';
            $args[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where .= ' AND created_at <= %s';
            $args[] = $date_to . ' 23:59:59';
        }

        $sql = "SELECT * FROM {$this->table_quotes} $where ORDER BY id DESC LIMIT 500";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

        $counts = array();
        foreach ($statuses as $key => $label) {
            $counts[$key] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_quotes} WHERE status=%s", $key));
        }

        ?>
        <div class="wrap srkqp-admin-wrap">
            <div class="srkqp-header">
                <div>
                    <h1>Quote Requests</h1>
                    <p>CRM-style quote inbox for WooCommerce product enquiries.</p>
                </div>
                <a class="button button-primary" href="<?php echo esc_url($this->export_url()); ?>">Export CSV</a>
            </div>

            <div class="srkqp-cards">
                <?php foreach ($statuses as $key => $label) : ?>
                    <div class="srkqp-card">
                        <span><?php echo esc_html($label); ?></span>
                        <strong><?php echo esc_html($counts[$key]); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <form class="srkqp-filter" method="get">
                <input type="hidden" name="page" value="srkqp-quotes">
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                <button class="button">Filter</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=srkqp-quotes')); ?>">Reset</a>
            </form>

            <div class="srkqp-table-card">
                <table class="widefat fixed striped srkqp-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows) : foreach ($rows as $row) : ?>
                        <tr>
                            <td>#<?php echo esc_html($row->id); ?></td>
                            <td>
                                <div class="srkqp-product-cell">
                                    <?php if ($row->product_image) : ?><img src="<?php echo esc_url($row->product_image); ?>" alt=""><?php endif; ?>
                                    <div>
                                        <strong><?php echo esc_html($row->product_name); ?></strong>
                                        <?php if ($row->product_url) : ?><a href="<?php echo esc_url($row->product_url); ?>" target="_blank">View</a><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html($row->customer_name); ?></strong><br>
                                <a href="mailto:<?php echo esc_attr($row->customer_email); ?>"><?php echo esc_html($row->customer_email); ?></a>
                            </td>
                            <td><?php echo esc_html($row->customer_phone); ?></td>
                            <td><?php echo esc_html(wp_trim_words($row->customer_message, 16)); ?></td>
                            <td>
                                <select class="srkqp-status-select" data-id="<?php echo esc_attr($row->id); ?>">
                                    <?php foreach ($statuses as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($row->status, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?php echo esc_html(mysql2date('M j, Y g:i A', $row->created_at)); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7">No quote requests found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="srkqp-live-log">
                <h2>Live Activity Log</h2>
                <div id="srkqp-live-log-list">Loading...</div>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        $email = get_option(self::OPT_ADMIN_EMAIL, get_option('admin_email'));
        ?>
        <div class="wrap srkqp-admin-wrap">
            <div class="srkqp-header">
                <div>
                    <h1>Quote Pro Settings</h1>
                    <p>Manage admin notification delivery and quote system behavior.</p>
                </div>
            </div>

            <form class="srkqp-settings-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('srkqp_save_settings'); ?>
                <input type="hidden" name="action" value="srkqp_save_settings">

                <label>
                    <span>Admin Notification Email</span>
                    <input type="email" name="admin_email" value="<?php echo esc_attr($email); ?>" required>
                    <small>New quote notifications will be sent to this email address.</small>
                </label>

                <button class="button button-primary">Save Settings</button>
            </form>
        </div>
        <?php
    }

    public function logs_page() {
        ?>
        <div class="wrap srkqp-admin-wrap">
            <div class="srkqp-header">
                <div>
                    <h1>Activity Log</h1>
                    <p>Recent quote activity, email events, and status changes.</p>
                </div>
            </div>
            <div class="srkqp-live-log srkqp-full-log">
                <div id="srkqp-live-log-list">Loading...</div>
            </div>
        </div>
        <?php
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('srkqp_save_settings');

        $email = sanitize_email($_POST['admin_email'] ?? '');
        if ($email && is_email($email)) {
            update_option(self::OPT_ADMIN_EMAIL, $email);
            $this->log_activity('Admin notification email updated', 'settings');
        }

        wp_safe_redirect(admin_url('admin.php?page=srkqp-settings&updated=1'));
        exit;
    }

    public function ajax_update_status() {
        check_ajax_referer('srkqp_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;

        $id = absint($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $statuses = $this->get_statuses();

        if (!$id || !isset($statuses[$status])) {
            wp_send_json_error(array('message' => 'Invalid status.'));
        }

        $wpdb->update($this->table_quotes, array(
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ), array('id' => $id));

        $this->log_activity('Quote #' . $id . ' status changed to ' . $statuses[$status], 'status', $id);

        wp_send_json_success(array('message' => 'Status updated.'));
    }

    public function ajax_get_logs() {
        check_ajax_referer('srkqp_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$this->table_logs} ORDER BY id DESC LIMIT 20");

        ob_start();
        if ($logs) {
            foreach ($logs as $log) {
                echo '<div class="srkqp-log-row"><span>' . esc_html(mysql2date('M j, Y g:i A', $log->created_at)) . '</span><strong>' . esc_html(ucfirst($log->log_type)) . '</strong><p>' . esc_html($log->message) . '</p></div>';
            }
        } else {
            echo '<p>No activity found.</p>';
        }

        wp_send_json_success(array('html' => ob_get_clean()));
    }

    private function log_activity($message, $type = 'info', $quote_id = 0) {
        global $wpdb;
        $wpdb->insert($this->table_logs, array(
            'quote_id' => absint($quote_id),
            'message' => sanitize_text_field($message),
            'log_type' => sanitize_text_field($type),
            'created_at' => current_time('mysql'),
        ));
    }

    private function export_url() {
        return wp_nonce_url(admin_url('admin.php?page=srkqp-quotes&srkqp_export=1'), 'srkqp_export_quotes');
    }

    public function handle_export() {
        if (!is_admin() || empty($_GET['srkqp_export'])) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied.');
        }

        check_admin_referer('srkqp_export_quotes');

        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_quotes} ORDER BY id DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=quote-requests-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Product Name', 'Product URL', 'Customer Name', 'Email', 'Phone', 'Message', 'Status', 'Created At'));

        foreach ($rows as $row) {
            fputcsv($output, array(
                $row['id'],
                $row['product_name'],
                $row['product_url'],
                $row['customer_name'],
                $row['customer_email'],
                $row['customer_phone'],
                $row['customer_message'],
                $row['status'],
                $row['created_at'],
            ));
        }

        fclose($output);
        exit;
    }
}

SRKPICS_Quote_Pro::instance();
