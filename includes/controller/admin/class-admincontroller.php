<?php

namespace AJ_UPLOADER\Includes\Controller\Admin;

if (! defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

class AdminController
{
    private $upload_dir;

    public function __construct()
    {
        $this->upload_dir = ABSPATH . '../';
        add_filter('woocommerce_product_data_tabs', [$this, 'aj_add_uploader_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'aj_add_uploader_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'aj_save_uploader_fields']);
        add_action('admin_post_download_uploaded_file', [$this, 'aj_upload_download_uploaded_file']);
        add_action('admin_post_accept_uploaded_file', [$this, 'aj_upload_accept_uploaded_file']);
        add_action('admin_post_reject_uploaded_file', [$this, 'aj_upload_reject_uploaded_file']);
        add_action('woocommerce_update_order', [$this, 'aj_save_reason_field']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'aj_display_uploads_in_order']);
        add_action('admin_enqueue_scripts', [$this, 'aj_uploader_register_assets']);
        add_filter('woocommerce_hidden_order_itemmeta', function ($hidden_meta) {
            $hidden_meta[] = '_aj_upload_custom_data'; // Unterdrücke die Anzeige
            return $hidden_meta;
        });

        add_action('woocommerce_after_order_itemmeta', [$this, 'aj_upload_display_custom_fields_in_admin_order'], 10, 3);
    }

    public function aj_uploader_register_assets()
    {
        $url =  trailingslashit(ajuploader_get_setting('url')) . 'assets/';
        $version = ajuploader_get_setting('version');


        wp_enqueue_script('aj-uploader-for-woo-admin-js', $url . 'js/aj-uploader-admin.js',  ['jquery'], $version, true);
    }



    public function aj_save_reason_field($order_id)
    {
        if (isset($_POST['reason'])) {
            remove_action('woocommerce_update_order', [$this, 'aj_save_reason_field']);
            // Hole die Bestellobjekt
            $order = wc_get_order($order_id);

            // Hole die hochgeladenen Dateien aus den Bestellmetadaten
            $uploads = $order->get_meta('_aj_uploaded_files', true);

            // Schleife über alle "reason"-Werte und speichere sie in den Metadaten
            foreach ($_POST['reason'] as $index => $reason) {
                if (isset($uploads[$index])) {
                    // Speichern des Grundes im Upload-Array
                    $uploads[$index]['reason'] = sanitize_textarea_field($reason);
                }
            }

            // Die Metadaten der Bestellung aktualisieren (HPOS-kompatibel)
            $order->update_meta_data('_aj_uploaded_files', $uploads);
            $order->save(); // Änderungen speichern
            add_action('woocommerce_update_order', [$this, 'aj_save_reason_field']);
        }
    }




    public function aj_display_uploads_in_order($order)
    {
        $uploads = get_post_meta($order->get_id(), '_aj_uploaded_files', true);

        if (empty($uploads)) {
            echo '<p>' . __('No files uploaded', 'aj-uploader') . '</p>';
            return;
        }

        echo '<h3>' . __('Uploaded Files', 'aj-uploader') . '</h3>';
        echo '<table class="widefat">';
        echo '<thead>
            <tr>
                <th>' . __('File Name', 'aj-uploader') . '</th>
                <th>' . __('Status', 'aj-uploader') . '</th>
                <th>' . __('Reason', 'aj-uploader') . '</th> <!-- Neue Spalte für den Grund -->
                <th>' . __('Action', 'aj-uploader') . '</th>
            </tr>
          </thead>';
        echo '<tbody>';

        foreach ($uploads as $index => $upload) {
            // Generiere den Download-Link mit Sicherheits-Nonce
            $download_url = wp_nonce_url(
                admin_url("admin-post.php?action=download_uploaded_file&orderid=" . $order->get_id() . "&filename=" . urlencode($upload['stored_filename'])),
                'wc_order_admin_download_nonce',
                'nonce'
            );

            $accept_url = wp_nonce_url(
                admin_url("admin-post.php?action=accept_uploaded_file&orderid=" . $order->get_id() . "&itemid=" . urlencode($index)),
                'wc_order_admin_accept_reject_nonce',
                'nonce'
            );

            $reject_url = wp_nonce_url(
                admin_url("admin-post.php?action=reject_uploaded_file&orderid=" . $order->get_id() . "&itemid=" . urlencode($index) . "&filename=" . urlencode($upload['stored_filename'])),
                'wc_order_admin_accept_reject_nonce',
                'nonce'
            );

            // Hole den Grund aus den Metadaten, falls vorhanden
            // Grund aus den Metadaten holen
            $reason = isset($upload['reason']) ? esc_html($upload['reason']) : '';

            echo '<tr>';
            echo '<td><a href="' . esc_url($download_url) . '" target="_blank">' . basename($upload['stored_filename']) . '</a></td>';
            echo '<td>' . esc_html($upload['status']) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($download_url) . '" class="button">' . __('Download', 'aj-uploader') . '</a>';
            echo '<a href="' . esc_url($accept_url) . '" class="button">' . __('Accept', 'aj-uploader') . '</a>';
            echo '<a href="' . esc_url($reject_url) . '" class="button">' . __('Reject', 'aj-uploader') . '</a>';

            // Formular für den Grund bei "pending"

            echo '<input type="text" name="reason[' . $index . ']" value="' . esc_attr($reason) . '" placeholder="' . __('Enter reason for acceptance', 'aj-uploader') . '" />';


            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }



    public function aj_upload_accept_uploaded_file()
    {
        if (
            !isset($_GET['orderid']) ||
            !isset($_GET['itemid']) ||
            !check_admin_referer('wc_order_admin_accept_reject_nonce', 'nonce')
        ) {
            wp_die(__('Invalid request.', 'aj-uploader'));
        }

        $order_id = absint($_GET['orderid']);
        $item_id = sanitize_text_field($_GET['itemid']);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'aj-uploader'));
        }

        $uploads = $order->get_meta('_aj_uploaded_files', true);

        if (isset($uploads[$item_id])) {
            // Den Grund aus den Metadaten holen, falls vorhanden
            

            // Status auf "accepted" setzen
            $uploads[$item_id]['status'] = 'accepted';

            // Die Metadaten der Bestellung aktualisieren
            $order->update_meta_data('_aj_uploaded_files', $uploads);
            $order->save();

            $to = $order->get_billing_email();
            $subject = __('Ihre hochgeladene Datei wurde erfolgreich akzeptiert', 'aj-uploader');

            // Lade die E-Mail-Vorlage aus der externen PHP-Datei
            $template = file_get_contents(trailingslashit(ajuploader_get_setting('path')) . '/templates/' . 'email-accept.php');

            // Ersetze Platzhalter im Template mit den tatsächlichen Werten
            $template = str_replace('{{customer_name}}', esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()), $template);
            $template = str_replace('{{uploaded_filename}}', esc_html($uploads[$item_id]['original_filename']), $template);

            // Setze die E-Mail-Header für HTML
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            // Sicherstellen, dass die E-Mail gesendet wird
            wp_mail($to, $subject, $template, $headers);

            $note = 'Die Datei wurde akzeptiert und der Kunde benachrichtigt.';

            //hinterlasse eine notiz bei der Order
            $order->add_order_note($note, false);
        }

        // Weiterleitung zur Bestellseite
        wp_redirect(admin_url("post.php?post=$order_id&action=edit"));
        exit;
    }



    public function aj_upload_reject_uploaded_file()
    {
        if (
            !isset($_GET['orderid']) ||
            !isset($_GET['itemid']) ||
            !isset($_GET['filename']) ||
            !check_admin_referer('wc_order_admin_accept_reject_nonce', 'nonce')
        ) {
            wp_die(__('Invalid request.', 'aj-uploader'));
        }

        $order_id = absint($_GET['orderid']);
        $item_id = sanitize_text_field($_GET['itemid']);
        $filename = sanitize_text_field($_GET['filename']);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'aj-uploader'));
        }

        $uploads = $order->get_meta('_aj_uploaded_files', true);
        if (isset($uploads[$item_id])) {
            $reason = isset($uploads[$item_id]['reason']) ? $uploads[$item_id]['reason'] : '';

              // E-Mail an den Kunden senden
              $to = $order->get_billing_email();
              $subject = __('Ihre hochgeladene Datei wurde abgelehnt', 'aj-uploader');
  
  
              // Lade die E-Mail-Vorlage aus der externen PHP-Datei
              $template = file_get_contents(trailingslashit(ajuploader_get_setting('path')) . '/templates/' . 'email-reject.php');
  
              // Ersetze Platzhalter im Template mit den tatsächlichen Werten
              $template = str_replace('{{customer_name}}', esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()), $template);
              $template = str_replace('{{order_url}}', esc_html('https://aj-druck.de/mein-konto'), $template);
              $template = str_replace('{{uploaded_filename}}', esc_html($uploads[$item_id]['original_filename']), $template);
              $template = str_replace('{{rejection_reason}}', esc_html($reason), $template); // Grund hinzufügen
  
              // Setze die E-Mail-Header für HTML
              $headers = ['Content-Type: text/html; charset=UTF-8'];
  
              // Sicherstellen, dass die E-Mail gesendet wird
              wp_mail($to, $subject, $template, $headers);

              $note = 'Die Datei wurde abgelehnt mit folgendem Grund: ' . esc_html($reason);

            //hinterlasse eine notiz bei der Order
            $order->add_order_note($note, false);


            $user_dir = $this->upload_dir . 'Kunden Files';
            $file_path = $user_dir . '/' . $filename;

            if (file_exists($file_path)) {
                unlink($file_path);
            }

            unset($uploads[$item_id]);
            $order->update_meta_data('_aj_uploaded_files', $uploads);
            $order->save();

          
        }

        wp_redirect(admin_url("post.php?post=$order_id&action=edit"));
        exit;
    }




    public function aj_upload_download_uploaded_file()
    {
        if (
            !isset($_GET['orderid']) ||
            !isset($_GET['filename']) ||
            !check_admin_referer('wc_order_admin_download_nonce', 'nonce')
        ) {
            wp_die(__('Invalid request.', 'aj-uploader'));
        }

        $order_id = absint($_GET['orderid']);
        $filename = sanitize_text_field($_GET['filename']);
        $user_dir = $this->upload_dir . 'Kunden Files'; // Das Verzeichnis für hochgeladene Dateien
        $file_path = $user_dir . '/' . $filename;

        // Sicherheitsüberprüfungen
        if (!file_exists($file_path)) {
            wp_die(__('File not found.', 'aj-uploader'));
        }

        if (strpos(realpath($file_path), realpath($user_dir)) !== 0) {
            wp_die(__('Unauthorized access.', 'aj-uploader'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'aj-uploader'));
        }

        // Datei sicher streamen
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }




    public function aj_add_uploader_tab($tabs)
    {
        $tabs['aj_uploader'] = array(
            'label'    => __('AJ Uploader', 'aj-uploader'),
            'target'   => 'aj_uploader_data',
            'class'    => array(),
            'priority' => 21,
        );
        return $tabs;
    }


    public function aj_add_uploader_tab_content()
    {
        global $post;

        // Hole bestehende Werte
        $enabled = get_post_meta($post->ID, '_aj_uploader_enabled', true);
        $allowed_formats = get_post_meta($post->ID, '_aj_uploader_allowed_formats', true);

?>
        <div id="aj_uploader_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Checkbox
                woocommerce_wp_checkbox(
                    array(
                        'id'    => '_aj_uploader_enabled',
                        'label' => __('Enable AJ Uploader', 'aj-uploader'),
                        'desc_tip' => true,
                        'description' => __('Activate AJ Uploader for this product.', 'aj-uploader'),
                        'value' => $enabled === 'yes' ? 'yes' : 'no'
                    )
                );

                // Textfeld für Dateiformate
                woocommerce_wp_text_input(
                    array(
                        'id'          => '_aj_uploader_allowed_formats',
                        'label'       => __('Allowed File Formats', 'aj-uploader'),
                        'desc_tip'    => true,
                        'description' => __('Enter allowed file formats separated by commas, e.g., jpg,png,pdf.', 'aj-uploader'),
                        'value'       => $allowed_formats,
                        'placeholder' => 'jpg,png,pdf',
                    )
                );
                ?>
            </div>
        </div>
<?php
    }


    // Daten speichern

    public function aj_save_uploader_fields($post_id)
    {
        // Checkbox-Wert speichern
        $enabled = isset($_POST['_aj_uploader_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_aj_uploader_enabled', sanitize_text_field($enabled));

        // Dateiformate speichern
        if (isset($_POST['_aj_uploader_allowed_formats'])) {
            $formats = sanitize_text_field($_POST['_aj_uploader_allowed_formats']);
            update_post_meta($post_id, '_aj_uploader_allowed_formats', $formats);
        }
    }

    public function aj_upload_display_custom_fields_in_admin_order($item_id, $item, $order)
    {
        // Überprüfen, ob die benutzerdefinierten Daten vorhanden sind
        $custom_data = $item->get_meta('_aj_upload_custom_data', true);


        if ($custom_data) {
            // Die serialisierten Daten de-serialisieren
            $custom_data = unserialize($custom_data);

            // Daten anzeigen
            echo '<p><strong>Höhe:</strong> ' . esc_html($custom_data['height']) . ' cm</p>';
            echo '<p><strong>Breite:</strong> ' . esc_html($custom_data['width']) . ' cm</p>';
            echo '<p><strong>Oberfläche:</strong> ' . esc_html($custom_data['surface']) . '</p>';
            echo '<p><strong>Farbe:</strong> ' . esc_html($custom_data['color']) . '</p>';
        }
    }
}
