<?php

namespace AJ_UPLOADER\Includes\Controller\Frontend;

if (! defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

use AJ_UPLOADER\Includes\Classes\Helper;
use AJ_UPLOADER\Includes\Classes\Render;

class ProductController
{
    private $required_fields = [
        'aj-upload-height',
        'aj-upload-width',
        'aj-upload-surface',
        'aj-upload-color',
    ];

    public function __construct()
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'aj_upload_fields_before_add_to_cart_button'], 10);
        add_action('woocommerce_after_add_to_cart_form', [$this, 'aj_upload_discount_table'], 10);
        add_filter('woocommerce_add_cart_item_data', [$this, 'aj_upload_add_custom_fields_to_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'aj_upload_save_custom_fields_in_order'], 10, 4);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'aj_upload_validate_custom_fields'], 20, 3);
        add_filter('woocommerce_get_item_data', [$this, 'aj_upload_display_custom_fields_in_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'aj_upload_adjust_cart_item_pricing']);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'aj_upload_adjust_cart_item_pricing_on_session'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'aj_upload_display_original_price_in_cart'], 10, 3);
    
    }


    public function aj_upload_fields_before_add_to_cart_button()
    {
        global $product;
        $product_id = $product->get_id();

        $pricedata = Helper::get_price($product_id);

        if (Helper::is_upload_enabled($product_id)) {
            echo Render::view('productfields', [
                'price' => $pricedata['price'],
                'extra_price' => $pricedata['extra_price']

            ]);
        }
    }

    public function aj_upload_discount_table()
    {
        global $product;
        $product_id = $product->get_id();

        if (Helper::is_upload_enabled($product_id)) {
            echo Render::view('discount_table');
        }
    }

    private function are_required_fields_set($data)
    {
        foreach ($this->required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false; // Ein erforderliches Feld fehlt
            }
        }
        return true; // Alle erforderlichen Felder sind vorhanden
    }

    public function aj_upload_add_custom_fields_to_cart($cart_item_data, $product_id)
    {

        if (!Helper::is_upload_enabled($product_id)) {
            return $cart_item_data; // Produkt nicht aktiv, daher keine Validierung nötig
        }
        if (!$this->are_required_fields_set($_POST)) {
            return $cart_item_data; // Rückgabe, wenn nicht alle erforderlichen Felder vorhanden sind
        }


        $cart_item_data['aj_upload_height'] = sanitize_text_field($_POST['aj-upload-height']);
        $cart_item_data['aj_upload_width'] = sanitize_text_field($_POST['aj-upload-width']);
        $cart_item_data['aj_upload_surface'] = sanitize_text_field($_POST['aj-upload-surface']);
        $cart_item_data['aj_upload_color'] = sanitize_text_field($_POST['aj-upload-color']);
        $cart_item_data['aj_upload_price'] = Helper::calcPrice($cart_item_data['aj_upload_height'], $cart_item_data['aj_upload_width'], $product_id);


        // Sicherstellen, dass die Daten im Warenkorb beibehalten werden
        $cart_item_data['unique_key'] = uniqid('', true);

        return $cart_item_data;
    }


    public function aj_upload_adjust_cart_item_pricing($cart)
    {

        if (is_admin() && !defined('DOING_AJAX')) {
            return; // Verhindert Ausführung im Admin-Bereich
        }

        if (empty($cart->get_cart())) {
            return; // Keine Artikel im Warenkorb, keine Aktionen notwendig
        }

        // Warenkorb durchlaufen und Preise anpassen
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            // Prüfen, ob der angepasste Preis (`aj_price`) vorhanden ist
            if (isset($cart_item['aj_upload_price'])) {
                // Ursprünglichen Preis speichern, falls er noch nicht gesetzt ist
                if (!isset($cart_item['original_price'])) {
                    $cart_item['original_price'] = $cart_item['aj_upload_price'];
                }

                $discounted_price = Helper::calcDiscountPrice($cart_item['aj_upload_height'], $cart_item['aj_upload_width'], $quantity, $cart_item['product_id'], $cart_item['aj_upload_price']);

                $product->set_price($discounted_price); // Rabattierten Preis setzen
                return $product;
            }
        }
    }

    public function aj_upload_adjust_cart_item_pricing_on_session($cart_item, $values, $key)
    {
        // Hier kannst du die gleiche Logik wie in adjust_cart_item_pricing anwenden
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        if (isset($cart_item['aj_upload_price'])) {
            $discounted_price = Helper::calcDiscountPrice($cart_item['aj_upload_height'], $cart_item['aj_upload_width'], $quantity, $cart_item['product_id'], $cart_item['aj_upload_price']);

            $product->set_price($discounted_price);

            return $cart_item;
        }
        return $cart_item;
    }

    public function aj_upload_display_original_price_in_cart($price_html, $cart_item, $cart_item_key)
    {
        // Prüfen, ob der Originalpreis vorhanden ist und größer als der aktuelle (rabattierte) Preis
        if (isset($cart_item['aj_upload_price']) && $cart_item['aj_upload_price'] > $cart_item['data']->get_price()) {
            // Formatieren des Originalpreises und des rabattierten Preises
            $original_price = wc_price($cart_item['aj_upload_price']); // Preis formatieren
            $discounted_price = wc_price($cart_item['data']->get_price());

            // Preis HTML mit durchgestrichenem Originalpreis und Rabattpreis erstellen
            $price_html = "<span class='original-price' style='text-decoration: line-through; color: #888;'>$original_price</span> <span class='discounted-price'>$discounted_price</span>";
        }

        return $price_html;
    }

    public function aj_upload_save_custom_fields_in_order($item, $cart_item_key, $values, $order)
    {
        $product_id = $values['data']->get_id();

        if (!Helper::is_upload_enabled($product_id)) {
            return; // Produkt nicht aktiv, daher keine Validierung nötig
        }

        // Überprüfen, ob die Felder gesetzt sind
        $height = isset($values['aj_upload_height']) ? $values['aj_upload_height'] : '';
        $width = isset($values['aj_upload_width']) ? $values['aj_upload_width'] : '';
        $surface = isset($values['aj_upload_surface']) ? $values['aj_upload_surface'] : '';
        $color = isset($values['aj_upload_color']) ? $values['aj_upload_color'] : '';

        // Die Felder in einem Array zusammenfassen
        $custom_data = [
            'height' => $height,
            'width' => $width,
            'surface' => $surface,
            'color' => $color
        ];


        $item->add_meta_data('_aj_upload_custom_data', serialize($custom_data), true);
    }


    public function aj_upload_validate_custom_fields($passed, $product_id, $quantity)
    {
        // Überprüfen, ob das Produkt für das Plugin aktiv ist
        if (!Helper::is_upload_enabled($product_id)) {
            return $passed; // Keine Validierung nötig
        }

        // Überprüfen, ob alle erforderlichen Felder ausgefüllt sind
        if (!$this->are_required_fields_set($_POST)) {
            wc_add_notice(__('Bitte füllen Sie alle erforderlichen Felder aus.', 'aj-upload-for-woo'), 'error');
            return false;
        }

        // Höhe validieren
        $ajuploadheight = $_POST['aj-upload-height'] ?? 0;
        $ajuploadwidth = $_POST['aj-upload-width'] ?? 0;

        if ($ajuploadheight < 2) {
            wc_add_notice(__('Höhe muss mindestens 2 cm sein.', 'aj-upload-for-woo'), 'error');
            return false;
        }

        // Einschränkungen prüfen
        $dimension_errors = $this->validate_dimensions($ajuploadwidth, $ajuploadheight);

        if (!empty($dimension_errors)) {
            wc_add_notice($dimension_errors, 'error');
            return false;
        }

        // Farben und Finish-Daten abrufen (serialisiert, daher mit unserialize decodieren)
        $serialized_colors = get_option('aj_vinyl_colorsdata', ''); // Abrufen der serialisierten Farbdaten
        $colors = unserialize($serialized_colors); // Deserialisieren

        // Überprüfen, ob die unserialisierten Farbdaten gültig sind
        if ($colors === false) {
            wc_add_notice(__('Fehler beim Laden der Farbdaten.', 'aj-upload-for-woo'), 'error');
            return false;
        }

        // Holen der ausgewählten Farbe und Finish
        $selected_color = $_POST['aj-upload-color'] ?? '';
        $selected_finish = $_POST['aj-upload-surface'] ?? '';

        // Überprüfen, ob die ausgewählte Farbe und Finish in der Liste der verfügbaren Farben und Finishes vorhanden sind
        $valid_color = false;
        foreach ($colors as $color) {
            if ($color['color'] === $selected_color && $color['finish'] === $selected_finish) {
                $valid_color = true;
                break;
            }
        }

        // Wenn die ausgewählte Farbe und Finish nicht gültig sind, eine Fehlermeldung ausgeben
        if (!$valid_color) {
            wc_add_notice(__('Die ausgewählte Farbe oder das Finish ist nicht gültig.', 'aj-upload-for-woo'), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Validiert Breite und Höhe und gibt einen Fehler zurück, falls ungültig.
     */
    private function validate_dimensions($width, $height)
    {
        if ($width > 70 && $height > 70) {
            return __('Entweder die Breite darf maximal 70 cm betragen oder die Höhe maximal 70 cm.', 'aj-upload-for-woo');
        }

        if ($width > 70 && $height > 200) {
            return __('Wenn die Breite mehr als 70 cm beträgt, darf die Höhe maximal 200 cm betragen.', 'aj-upload-for-woo');
        }

        if ($height > 70 && $width > 200) {
            return __('Wenn die Höhe mehr als 70 cm beträgt, darf die Breite maximal 200 cm betragen.', 'aj-upload-for-woo');
        }

        if ($height > 200) {
            return __('Die Höhe liegt über 200 cm.', 'aj-upload-for-woo');
        }

        if ($width > 200) {
            return __('Die Breite liegt über 200 cm.', 'aj-upload-for-woo');
        }

        return ''; // Keine Fehler
    }

    public function aj_upload_display_custom_fields_in_cart($item_data, $cart_item)
    {
        // Überprüfen, ob die benötigten Felder vorhanden sind
        if (isset($cart_item['aj_upload_height'], $cart_item['aj_upload_width'], $cart_item['aj_upload_surface'], $cart_item['aj_upload_color'])) {

            // Array mit den Feldnamen und Werten
            $custom_fields = [
                'Breite' => esc_html($cart_item['aj_upload_width'] . ' cm'),
                'Höhe' => esc_html($cart_item['aj_upload_height'] . ' cm'),
                'Oberfläche' => esc_html($cart_item['aj_upload_surface']),
                'Farbe' => esc_html($cart_item['aj_upload_color']),

            ];

            // Füge die benutzerdefinierten Felder zum Item-Daten-Array hinzu
            foreach ($custom_fields as $name => $value) {
                $item_data[] = array(
                    'name' => $name,
                    'value' => $value,
                );
            }
        }

        return $item_data;
    }





}
