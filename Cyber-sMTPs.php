<?php
/*
Plugin Name: CyberSMTPs
Plugin URI: https://cyberpanel.net/managed-email-service
Description: Easy to use SMTP Plugin.
Version: 1.0
Author: Usman Nasir
Author URI: https://cyberpanel.net/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CyberSMTPs
{

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'create_email_logs_table'));
        add_action('admin_menu', array($this, 'create_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('phpmailer_init', array($this, 'setup_smtp'));
        add_filter('wp_mail', array($this, 'log_email'), 10, 1); //
        add_action('admin_post_send_test_email', array($this, 'send_test_email'));
        add_action('admin_notices', array($this, 'smtp_settings_notice'));
    }

    public function smtp_settings_notice()
    {
        $options = get_option('cybersmtps_options');

        if (empty($options['smtp_host']) || empty($options['smtp_port']) ||
            empty($options['smtp_encryption']) || empty($options['smtp_username']) ||
            empty($options['smtp_password'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Your WordPress might not be able to send emails. Please', 'cybersmtps'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=cybersmtps'); ?>"><?php _e('configure email settings', 'cybersmtps'); ?></a>.
                </p>
            </div>
            <?php
        }
    }

    public function create_email_logs_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cybersmtps_email_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_to varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            message text NOT NULL,
            date_sent datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function create_menu()
    {
        add_options_page(
            'CyberSMTPs Settings',
            'CyberSMTPs',
            'manage_options',
            'cybersmtps',
            array($this, 'settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('cybersmtps_settings', 'cybersmtps_options', array($this, 'sanitize_options'));
    }

    public function sanitize_options($options)
    {
        $options['from_email'] = sanitize_email($options['from_email']);
        $options['from_name'] = sanitize_text_field($options['from_name']);
        $options['smtp_host'] = sanitize_text_field($options['smtp_host']);
        $options['smtp_port'] = absint($options['smtp_port']);
        $options['smtp_encryption'] = sanitize_text_field($options['smtp_encryption']);
        $options['smtp_username'] = sanitize_text_field($options['smtp_username']);
        $options['smtp_password'] = sanitize_text_field($options['smtp_password']);
        return $options;
    }

    public function settings_page()
    {
        ?>
        <div class="wrap">
            <h1>CyberSMTPs Settings</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#smtp-settings" class="nav-tab nav-tab-active">SMTP Settings</a>
                <a href="#send-test-email" class="nav-tab">Send Test Email</a>
                <a href="#email-logs" class="nav-tab">Email Logs</a>
            </h2>
            <div id="smtp-settings" class="tab-content">
                <h1 style="margin: 1%">
                    You can get FREE Email/SMTP details <a
                            href="https://cyberpanel.net/managed-email-service?utm_source=from-plugin-cybersmtp&utm_medium=from-plugin-cybersmtp&utm_campaign=from-plugin-cybersmtp&utm_id=from-plugin-cybersmtp&utm_term=from-plugin-cybersmtp">here.</a>

                </h1>
                <form method="post" action="options.php">
                    <?php settings_fields('cybersmtps_settings'); ?>
                    <?php $options = get_option('cybersmtps_options'); ?>
                    <h2>Sender Settings</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">From Email</th>
                            <td><input type="email" name="cybersmtps_options[from_email]"
                                       value="<?php echo isset($options['from_email']) ? esc_attr($options['from_email']) : ''; ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">From Name</th>
                            <td><input type="text" name="cybersmtps_options[from_name]"
                                       value="<?php echo isset($options['from_name']) ? esc_attr($options['from_name']) : ''; ?>"/>
                            </td>
                        </tr>
                    </table>
                    <h2>SMTP Settings</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">SMTP Host</th>
                            <td><input type="text" name="cybersmtps_options[smtp_host]"
                                       value="<?php echo isset($options['smtp_host']) ? esc_attr($options['smtp_host']) : ''; ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SMTP Port</th>
                            <td><input type="number" name="cybersmtps_options[smtp_port]"
                                       value="<?php echo isset($options['smtp_port']) ? esc_attr($options['smtp_port']) : ''; ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Encryption</th>
                            <td>
                                <select name="cybersmtps_options[smtp_encryption]">
                                    <option value="none" <?php selected(isset($options['smtp_encryption']) ? $options['smtp_encryption'] : '', 'none'); ?>>
                                        None
                                    </option>
                                    <option value="ssl" <?php selected(isset($options['smtp_encryption']) ? $options['smtp_encryption'] : '', 'ssl'); ?>>
                                        SSL
                                    </option>
                                    <option value="tls" <?php selected(isset($options['smtp_encryption']) ? $options['smtp_encryption'] : '', 'tls'); ?>>
                                        TLS
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SMTP Username</th>
                            <td><input type="text" name="cybersmtps_options[smtp_username]"
                                       value="<?php echo isset($options['smtp_username']) ? esc_attr($options['smtp_username']) : ''; ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">SMTP Password</th>
                            <td><input type="password" name="cybersmtps_options[smtp_password]"
                                       value="<?php echo isset($options['smtp_password']) ? esc_attr($options['smtp_password']) : ''; ?>"/>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <div id="send-test-email" class="tab-content" style="display: none;">
                <h2>Send Test Email</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="send_test_email">
                    <?php wp_nonce_field('send_test_email_nonce'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">To Email</th>
                            <td><input type="email" name="test_email_to" required/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Subject</th>
                            <td><input type="text" name="test_email_subject" required/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Message</th>
                            <td><textarea name="test_email_message" required></textarea></td>
                        </tr>
                    </table>
                    <?php submit_button('Send Test Email'); ?>
                </form>
            </div>
            <div id="email-logs" class="tab-content" style="display: none;">
                <h2>Email Logs</h2>
                <?php $this->display_email_logs(); ?>
            </div>
        </div>
        <script>
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                    document.querySelector(this.getAttribute('href')).style.display = 'block';
                    this.classList.add('nav-tab-active');
                });
            });
        </script>
        <?php
    }

    public function setup_smtp($phpmailer)
    {
        $options = get_option('cybersmtps_options', array());

        $phpmailer->isSMTP();
        $phpmailer->Host = isset($options['smtp_host']) ? $options['smtp_host'] : '';
        $phpmailer->Port = isset($options['smtp_port']) ? $options['smtp_port'] : '';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = isset($options['smtp_username']) ? $options['smtp_username'] : '';
        $phpmailer->Password = isset($options['smtp_password']) ? $options['smtp_password'] : '';
        $phpmailer->From = isset($options['from_email']) ? $options['from_email'] : '';
        $phpmailer->FromName = isset($options['from_name']) ? $options['from_name'] : '';

        if (isset($options['smtp_encryption']) && $options['smtp_encryption'] !== 'none') {
            $phpmailer->SMTPSecure = $options['smtp_encryption'];
        } else {
            $phpmailer->SMTPSecure = '';
        }
    }

    public function log_email($args)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cybersmtps_email_logs';
        $wpdb->insert(
            $table_name,
            array(
                'email_to' => $args['to'],
                'subject' => $args['subject'],
                'message' => $args['message']
            )
        );
        return $args;
    }

    public function display_email_logs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cybersmtps_email_logs';

        // Pagination variables
        $per_page = 10; // Number of logs per page
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $offset = ($page - 1) * $per_page;

        // Ensure offset is non-negative
        if ($offset < 0) {
            $offset = 0;
        }

        // Retrieve logs with pagination
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY date_sent DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        if ($logs) {
            echo '<table class="widefat fixed" cellspacing="0">';
            echo '<thead><tr><th>ID</th><th>To</th><th>Subject</th><th>Message</th><th>Date Sent</th></tr></thead>';
            echo '<tbody>';
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log->id) . '</td>';
                echo '<td>' . esc_html($log->email_to) . '</td>';
                echo '<td>' . esc_html($log->subject) . '</td>';
                echo '<td>' . esc_html($log->message) . '</td>';
                echo '<td>' . esc_html($log->date_sent) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';

            // Pagination links
            $total_logs = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
            $total_pages = ceil($total_logs / $per_page);

            if ($total_pages > 1) {
                $page_links = paginate_links(array(
                    'base' => add_query_arg('page', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous'),
                    'next_text' => __('Next &raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                ));

                if ($page_links) {
                    echo '<div class="tablenav">';
                    echo '<div class="tablenav-pages">';
                    echo '<span class="pagination-count">';
                    printf(
                        __('%d log(s)'),
                        $total_logs
                    );
                    echo '</span>';
                    echo '<span class="pagination-links">';
                    echo $page_links;
                    echo '</span>';
                    echo '</div>';
                    echo '</div>';
                }
            }
        } else {
            echo '<p>No email logs found.</p>';
        }
    }



    public function send_test_email()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'send_test_email_nonce')) {
            wp_die('Nonce verification failed.');
        }

        $to = sanitize_email($_POST['test_email_to']);
        $subject = sanitize_text_field($_POST['test_email_subject']);
        $message = sanitize_textarea_field($_POST['test_email_message']);

        $result = wp_mail($to, $subject, $message);

        if ($result) {
            wp_redirect(add_query_arg('test_email_sent', 'success', wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('test_email_sent', 'failure', wp_get_referer()));
        }
        exit;
    }
}

new CyberSMTPs();
?>
