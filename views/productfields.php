<div class="aj-upload-order-container">

    <!-- Eingabefelder für Höhe und Breite -->
    <div class="aj-upload-dimension-container">
        <div class="aj-upload-dimension-field">
            <label for="aj-upload-height">Höhe (in cm):</label>
            <input type="number" id="aj-upload-height" name="aj-upload-height" required min="0.01" step="any" value="10" placeholder="Höhe in cm" oninput="aj_upload_calcPrice()">

        </div>

        <div class="aj-upload-dimension-field">
            <label for="aj-upload-width">Breite (in cm):</label>
            <input type="number" id="aj-upload-width" name="aj-upload-width" required min="0.01" step="any" value="10" placeholder="Breite in cm" oninput="aj_upload_calcPrice()">

        </div>
    </div>
    <div id="aj-upload-error-container"></div>


    <!-- Dropdown für Oberfläche -->
    <div class="aj-upload-dropdown-container">
        <label for="aj-upload-surface">Wählen Sie die Oberfläche:</label>
        <select id="aj-upload-surface" name="aj-upload-surface" required>
            <option value="Glanz">Glänzend</option>
            <option value="Matt">Matt</option>
        </select>
    </div>

    <!-- Dropdown für Farbe -->
    <div class="aj-upload-dropdown-container">
        <label for="aj-upload-color">Wählen Sie die Farbe:</label>
        <select id="aj-upload-color" name="aj-upload-color" required>
            <!-- Farben werden hier dynamisch geladen -->
        </select>
    </div>


    <!-- Divider (Strich) nach den Eingabefeldern -->
    <div class="aj-upload-divider"></div>

    <!-- Preisbereich (rechts ausgerichtet) -->
    <div class="aj-upload-price-container">
        <div class="aj-upload-price-line">
            <div class="aj-upload-price-text">Preis:</div>
            <div id="aj-upload-price" class="aj-upload-price"><strong>8,47 €</strong></div>
        </div>
        <div class="aj-upload-price-per-item">pro stk.</div>
        <?php

        if (has_filter('aj_vinyl_legal_info')) {
            // Wenn der Filter registriert ist, wende ihn an
            apply_filters('aj_vinyl_legal_info', $value);
        }
        ?>

    </div>





    <input type="hidden" id="aj-upload-get-price" value="<?= $price ?>">
    <input type="hidden" id="aj-upload-get-extra-price" value="<?= $extra_price ?>">



</div>