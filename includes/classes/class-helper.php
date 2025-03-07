<?php 

namespace AJ_UPLOADER\Includes\Classes;

class Helper {
    public static function is_upload_enabled($product_id) {
        $enabled = get_post_meta($product_id, '_aj_uploader_enabled', true);
        return $enabled === 'yes';
    }

    public static function get_allowed_formats($product_id) {
        $formats = get_post_meta($product_id, '_aj_uploader_allowed_formats', true);
        return array_map('trim', explode(',', $formats)); // Array der Formate
    }

    public static function get_price($product_id)
    {
        return [
            'price' => 50,
            'extra_price' => 7.97,
        ];
    }

    public static function qmCalc($height, $width)
    {
        $qm = round(($height / 100) * ($width / 100), 4);
        if ($qm === 0) {
            $qm = 0.001;
        }
        return $qm;
    }

    public static function getDiscount($originalPrice, $area)
    {

        $product_price = $originalPrice / $area;
        $minPreis = 28;
        $target_price = max($product_price * (1 - 94 / 100), $minPreis);


        // Gesamtpreisreduktion, die auf den Rabatt angewendet werden muss
        $price_reduction = $product_price - $target_price;

        // Die angegebenen Stückzahlen, jetzt mit 1 Stück als symbolische Menge
        $quantities = [1, 2, 5, 10, 20, 30, 50, 100, 250, 500, 1000];

        // Berechnung der Anzahl der Schritte - Rabatt beginnt ab Menge 2 bis 1000
        $steps = count($quantities);

        // Berechnung des Rabatts pro Schritt
        $discount_per_step = $price_reduction / ($steps - 1); // Rabatt pro Schritt

        // Array für das Ergebnis
        $result = [];

        foreach ($quantities as $index => $quantity) {
            // Rabatt nur für Stückzahl >= 2 berechnen
            if ($quantity >= 2) {
                // Berechnung des Rabatts für die aktuelle Menge
                $discount = $discount_per_step * $index; // Rabatt beginnt bei Menge 2
                $end_price = $product_price - $discount;

                // Sicherstellen, dass der Rabatt den maximalen Rabatt nicht überschreitet
                if ($end_price < $target_price) {
                    $end_price = $target_price;
                    $discount = $product_price - $target_price;
                }
            } else {
                // Für Menge 1 gibt es keinen Rabatt, nur den vollen Preis
                $discount = 0;
                $end_price = $product_price;
            }

            // Werte zum Ergebnis-Array hinzufügen
            $result[] = [
                'quantity' => $quantity,
                'discount' => round($discount, 2),
                'end_price' => $end_price
            ];
        }

        // Array zurückgeben
        return $result;
    }

    public static function calcDiscountPrice($height, $width, $quantity, $product_id, $originalPrice)
    {
        $area = self::qmCalc($height, $width);
        $discountArray = self::getDiscount($originalPrice, $area);

        // Passenden Rabatt anhand der Menge aus der Rabatt-Tabelle finden
        $newPriceQm = 0; // Standardmäßig kein Rabatt

        foreach ($discountArray as $discount) {
            if ($quantity >= $discount['quantity']) {
                $newPriceQm = $discount['end_price'];
            } else {
                break; // Überspringe die verbleibenden Werte, da die Tabelle aufsteigend ist
            }
        }

        return round($newPriceQm * $area, 2);
    }


    public static function calcPrice ($height, $width, $product_id)
    {
        $area = self::qmCalc($height, $width);
        $price = self::get_price($product_id);
        $perQmPrice = $price['price'];
        $extra_cost = $price['extra_price'];

        $finalPrice = ($area * $perQmPrice) + $extra_cost;

        return round($finalPrice, 2);

    }


}
