<?php

namespace AJ_UPLOADER\Includes\Controller\Frontend;

use enshrined\svgSanitize\Sanitizer;
use AJ_UPLOADER\Includes\Classes\Helper;
use AJ_UPLOADER\Includes\Classes\Render;

if (!defined('ABSPATH')) {
    exit;
}

class PublicController
{
    private $upload_dir;

    public function __construct()
    {
        $this->upload_dir = ABSPATH . '../';
        add_action('wp_enqueue_scripts', [$this, 'aj_uploader_register_assets']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'aj_add_upload_section'], 10, 1);
        add_action('wp_ajax_wc_handle_file_upload', [$this, 'handle_file_upload']);
        add_action('wp_ajax_nopriv_wc_handle_file_upload', [$this, 'handle_file_upload']);
        add_action('woocommerce_order_item_meta_end', [$this, 'aj_upload_display_custom_fields_in_order_table'], 10, 4);
        new ProductController();
    }






    public function aj_upload_display_custom_fields_in_order_table($item_id, $item, $order, $plain_text)
    {
        // Hole die benutzerdefinierten Daten
        $custom_data = $item->get_meta('_aj_upload_custom_data', true);

        // Überprüfen, ob die Daten existieren
        if ($custom_data) {
            $custom_data = maybe_unserialize($custom_data); // Daten ent-serialisieren

            if (is_array($custom_data)) {
                // Daten formatieren und anzeigen
                echo '<p><strong>Höhe:</strong> ' . esc_html($custom_data['height']) . ' cm</p>';
                echo '<p><strong>Breite:</strong> ' . esc_html($custom_data['width']) . ' cm</p>';
                echo '<p><strong>Oberfläche:</strong> ' . esc_html($custom_data['surface']) . '</p>';
                echo '<p><strong>Farbe:</strong> ' . esc_html($custom_data['color']) . '</p>';
            }
        }
    }


    public function aj_uploader_register_assets()
    {

        $url =  trailingslashit(ajuploader_get_setting('url')) . 'assets/';
        $version = ajuploader_get_setting('version');
        wp_enqueue_script('jquery');

        wp_enqueue_style(
            'aj-uploader-for-woo-style', // Handle des Styles
            $url . 'css/aj-woo-uploader.css', // Pfad zur CSS-Datei
            array(), // Abhängigkeiten
            $version // Version des Styles
        );

        wp_enqueue_script(
            'aj-uploader-for-woo-script', // Handle des Skripts
            $url . 'js/aj-woo-uploader.js', // Pfad zur JS-Datei
            array('jquery'), // Abhängigkeiten (z.B. jQuery)
            $version, // Version des Skripts
            true // Das Skript wird im Footer geladen
        );

        wp_localize_script('aj-uploader-for-woo-script', 'aj_uploads_for_woo', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_order_upload_nonce'),
        ]);

        $colors = get_option('aj_vinyl_colorsdata', serialize([]));
        $colors = unserialize($colors);

        // JSON-Daten erstellen
        $colors_json = json_encode($colors);
        wp_add_inline_script('aj-uploader-for-woo-script', 'var ajuploadColors = ' . $colors_json . ';', 'before');
    }


    public function aj_add_upload_section($order_id)
    {
        // Hole die Bestellung und die zugehörigen Artikel
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        // Hole die gespeicherten Upload-Daten und deserialisiere sie
        $uploaded = $order->get_meta('_aj_uploaded_files', true);
        $uploaded = maybe_unserialize($uploaded); // Entschlüsselung der gespeicherten Daten

        $position = 1;

        foreach ($items as $item) {
            // Holen der Produkt-ID und Custom-Daten für jedes Item
            $product_id = $item->get_product_id();

            // Überprüfe, ob der Upload für dieses Produkt aktiviert ist
            if (Helper::is_upload_enabled($product_id)) {
                $allowed_formats = Helper::get_allowed_formats($product_id);
                $allowed_formats_text = implode(', ', $allowed_formats);

                // Hole die Custom-Daten für das Item
                $custom_data = $item->get_meta('_aj_upload_custom_data', true);
                $custom_data = maybe_unserialize($custom_data);

                // Hole die Item-ID des aktuellen Artikels
                $item_id = $item->get_id();
                $file_data = isset($uploaded[$item_id]) ? $uploaded[$item_id] : null;

                // Überprüfe, ob der Upload-Status für dieses Item vorhanden ist
                if (isset($uploaded[$item_id]) && $uploaded[$item_id]['uploaded']) {
                    // Wenn bereits hochgeladen, gib 'UPLOADED' aus
                    $original_filename = esc_html($file_data['original_filename']);
                    $upload_status = $file_data['uploaded'] ? 'Hochgeladen' : 'Fehler beim Hochladen';
                    $review_status = $file_data['status'];

                    echo Render::view('uploaded_file', [
                        'hoehe' => esc_html($custom_data['height']),
                        'breite' => esc_html($custom_data['width']),
                        'original_filename' => $original_filename,
                        'upload_status' => $upload_status,
                        'review_status' => $review_status,


                    ]);
                } else {
                    // Wenn noch nichts hochgeladen wurde, zeige das Upload-Formular
                    echo Render::view('upload-form', [
                        'orderID' => $item->get_order_id(),
                        'itemID' => $item_id,
                        'allowed_formats' => $allowed_formats_text,
                        'itemnr' => $position,
                        'hoehe' => esc_html($custom_data['height']),
                        'breite' => esc_html($custom_data['width']),
                    ]);
                }

                $position++; // Zähle die Position für jedes Item
            }
        }
    }



    public function handle_file_upload()
    {
        if (
            !isset($_POST['orderid']) ||
            !isset($_POST['itemid']) ||
            !isset($_FILES['file']) ||
            !check_ajax_referer('wc_order_upload_nonce', 'nonce', false)
        ) {
            return wp_send_json_error(['message' => 'Nicht berechtigt oder ungültige Anfrage.']);
        }

        $orderid = absint($_POST['orderid']);
        $itemid = sanitize_text_field($_POST['itemid']);
        $filepath = $_FILES['file']['tmp_name'];
        $originalFilename = sanitize_text_field($_FILES['file']['name']); // Ursprünglicher Dateiname
        $fileSize = filesize($filepath);
        $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
        $filetype = finfo_file($fileinfo, $filepath);
        finfo_close($fileinfo);

        $user_id = 'Kunden Files';
        $user_dir = $this->upload_dir . $user_id;
        $max_size = 31457280; // 30 MB
        $max_size_show = $max_size / 1024 / 1024;

        $allowedTypes = [
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/postscript' => 'eps',
            'application/illustrator' => 'ai',
        ];

        // Validierungen
        if (!in_array($filetype, array_keys($allowedTypes))) {
            return wp_send_json_error(['message' => 'Das Format wird nicht unterstützt.']);
        }

        if ($fileSize === 0) {
            return wp_send_json_error(['message' => 'Die Datei ist leer.']);
        }

        if ($fileSize > $max_size) {
            return wp_send_json_error(['message' => 'Die Datei ist zu groß, nur bis ' . $max_size_show . ' MB erlaubt.']);
        }

        if (!is_dir($user_dir)) {
            mkdir($user_dir, 0750, true);
        }

        if ($filetype == 'image/svg+xml') {
            $svgContent = file_get_contents($filepath);

            // Entferne potenziell schadhafte Skripte aus SVG
            $sanitizer = new Sanitizer();
            $cleanSVG = $sanitizer->sanitize($svgContent);

            // Überprüfen auf potenziell gefährliche Tags
            if (preg_match('/<(iframe|object|embed|image|foreignObject)/i', $cleanSVG)) {
                return wp_send_json_error(['message' => 'SVG-Datei enthält unerlaubte Elemente.']);
            }

            // Überprüfen auf Text-Elemente
            if (preg_match('/<text.*?>.*?<\/text>/is', $cleanSVG)) {
                return wp_send_json_error(['message' => 'SVG-Datei enthält Text-Elemente, die für das Plotten nicht geeignet sind.']);
            }

            // Überprüfen auf Pfad-Elemente und sicherstellen, dass alle Pfade geschlossen sind
            if (!preg_match_all('/<path.*?d="([^"]+)"/is', $cleanSVG, $matches)) {
                return wp_send_json_error(['message' => 'SVG-Datei enthält keine Pfad-Elemente, die zum Plotten geeignet sind.']);
            }

            foreach ($matches[1] as $path) {
                if (strpos(strtoupper($path), 'Z') === false) {
                    return wp_send_json_error(['message' => 'Mindestens ein Pfad in der SVG-Datei ist nicht geschlossen.']);
                }
            }

            // Speichern der bereinigten SVG-Datei
            $filename = uniqid($orderid . '-' . $itemid . '_', true); // Generierter Dateiname für das bereinigte SVG
            $extension = 'svg';

            // Schreibe den bereinigten SVG-Inhalt in die neue Datei
            file_put_contents($filepath, $cleanSVG);
        } else {
            // Wenn es keine SVG-Datei ist, verschiebe einfach die Datei ohne weitere Bearbeitung
            $filename = uniqid($orderid . '-' . $itemid . '_', true); // Generierter Dateiname
            $extension = $allowedTypes[$filetype];
            
        }




        $newFilepath = $user_dir . "/" . $filename . "." . $extension;

        // Datei verschieben
        if (is_uploaded_file($filepath)) {
            if (move_uploaded_file($filepath, $newFilepath)) {
                // Hole die Bestellung
                $order = wc_get_order($orderid);
                if ($order) {
                    // Meta-Daten vorbereiten
                    $meta_data = [
                        'uploaded' => true,
                        'status' => 'pending',
                        'original_filename' => $originalFilename,
                        'stored_filename' => $filename . "." . $extension,
                        'itemid' => $itemid,
                    ];

                    // Hole existierende Meta-Daten für die Bestellung (HPOS-kompatibel)
                    $existing_meta = $order->get_meta('_aj_uploaded_files', true);
                    if (!$existing_meta) {
                        $existing_meta = [];
                    }

                    // Füge die neuen Meta-Daten hinzu
                    $existing_meta[$itemid] = $meta_data;

                    // Speichere die Meta-Daten
                    $order->update_meta_data('_aj_uploaded_files', $existing_meta);
                    $order->save(); // Stelle sicher, dass die Änderungen gespeichert werden
                }

                return wp_send_json_success(['message' => 'Datei erfolgreich hochgeladen.']);
            } else {
                return wp_send_json_error(['message' => 'Fehler beim Hochladen der Datei.']);
            }
        } else {
            return wp_send_json_error(['message' => 'Ungültiger Datei-Upload.']);
        }
    }
}
