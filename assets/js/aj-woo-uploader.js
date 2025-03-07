document.addEventListener('DOMContentLoaded', () => {
    let aj_upload_feldcheck = document.getElementById('aj-upload-surface');
    if (aj_upload_feldcheck){
        aj_upload_ProductPage();
        aj_upload_calcPrice();
        aj_upload_inputchanger();
    }

    aj_upload_accountPageUploader();


});

function aj_upload_accountPageUploader() {
    const uploadAreas = document.querySelectorAll('.upload-area');

    uploadAreas.forEach(uploadArea => {
        const fileInput = uploadArea.querySelector('.file-input');
        const itemid = uploadArea.querySelector('.itemid').value; // Holt die itemid aus dem versteckten Input-Feld
        const progressBar = document.querySelector(`#progress-bar-item-${itemid} .progress`);
        const uploadList = document.querySelector(`#upload-list-item-${itemid}`);

        // Klick-Event für das Upload-Area
        uploadArea.addEventListener('click', () => {
            if (fileInput.style.display !== 'none') {
                fileInput.click();
            }
        });

        // Eventlistener für Änderungen (Dateiauswahl)
        fileInput.addEventListener('change', aj_upload_handleFileUpload);

        // Drag and Drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (fileInput.style.display !== 'none') {
                uploadArea.classList.add('dragover');
            }
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0 && fileInput.style.display !== 'none') {
                aj_upload_handleFileUpload({ target: { files } });
            }
        });

        // Funktion zum Hochladen der Datei
        function aj_upload_handleFileUpload(event) {
            const files = event.target.files;
            if (files.length === 0) return;

            // Zeige den Fortschrittsbalken
            progressBar.style.display = 'block';
            progressBar.style.width = '0%';

            // Verstecke das Upload-Feld nach Auswahl der Datei
            fileInput.style.display = 'none';

            Array.from(files).forEach((file) => {
                const listItem = document.createElement('li');
                listItem.innerHTML = `
                    <span class="file-name">${file.name}</span>
                    <span class="status">Wird hochgeladen...</span>
                `;
                uploadList.appendChild(listItem);

                aj_upload_uploadFile(file, listItem, itemid);
            });
        }

        // Datei hochladen
        function aj_upload_uploadFile(file, listItem, itemid) {
            const status = listItem.querySelector('.status');
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'wc_handle_file_upload');
            formData.append('nonce', aj_uploads_for_woo.nonce);
            formData.append('orderid', uploadArea.querySelector('.orderid').value);
            formData.append('itemid', itemid);

            const xhr = new XMLHttpRequest();

            // Fortschrittsanzeige
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                }
            });

            // Erfolgreiches Hochladen
            xhr.addEventListener('load', () => {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    status.textContent = 'Erfolgreich hochgeladen';
                    disableUploadArea(uploadArea, itemid); // Upload-Bereich deaktivieren
                } else {
                    status.textContent = 'Fehler: ' + response.data.message;
                    fileInput.style.display = 'block';
                }
            });

            // Fehlerereignis
            xhr.addEventListener('error', () => {
                status.textContent = 'Fehler beim Hochladen.';
                fileInput.style.display = 'block';
            });

            // Sende die Anfrage
            xhr.open('POST', aj_uploads_for_woo.ajax_url, true);
            xhr.send(formData);
        }

        // Deaktiviert den Upload-Bereich nach einem erfolgreichen Upload
        function disableUploadArea(uploadArea, itemid) {
            location.reload();
            // Entferne Events und mache den Bereich inaktiv
            uploadArea.style.pointerEvents = 'none';
            uploadArea.style.opacity = '0.5'; // Optionale visuelle Anpassung

            const completeMessage = document.createElement('p');
            completeMessage.textContent = 'Upload abgeschlossen';
            completeMessage.style.color = 'green';
            completeMessage.style.fontWeight = 'bold';
            uploadArea.appendChild(completeMessage);
        }
    });
}



function aj_upload_ProductPage() {

    const surfaceDropdown = document.getElementById('aj-upload-surface');
    const colorDropdown = document.getElementById('aj-upload-color');


    // Funktion, um das Farbdropdown zu aktualisieren
    function aj_upload_updateColorOptions() {
        const selectedSurface = surfaceDropdown.value;

        // Filtern der Farben basierend auf der Oberfläche (Glanz oder Matt)
        const filteredColors = ajuploadColors.filter(item => item.finish === selectedSurface);

        // Leere das Farbdropdown
        colorDropdown.innerHTML = '';

        // Füge die gefilterten Farben dem Dropdown hinzu
        filteredColors.forEach(item => {
            const option = document.createElement('option');
            option.value = item.color;
            option.textContent = item.color;
            colorDropdown.appendChild(option);
        });
    }

    // Initialisieren der Farbauswahl basierend auf der Standardoberfläche
    aj_upload_updateColorOptions();

    // Wenn der Benutzer die Oberfläche ändert, die Farben aktualisieren
    surfaceDropdown.addEventListener('change', aj_upload_updateColorOptions);


}

function aj_upload_calcPrice() {
    // Holen der Preise aus den versteckten Eingabefeldern
    const ajuploadprice = parseFloat(document.getElementById('aj-upload-get-price').value);
    const ajuploadextraprice = parseFloat(document.getElementById('aj-upload-get-extra-price').value);

    // Holen der Eingabewerte für Höhe und Breite in cm
    let ajuploadheight = document.getElementById('aj-upload-height').value;
    let ajuploadwidth = document.getElementById('aj-upload-width').value;

    // Umgehen des Komma-Problems bei Dezimalzahlen
    ajuploadheight = ajuploadheight.replace(',', '.');
    ajuploadwidth = ajuploadwidth.replace(',', '.');

    if (isNaN(ajuploadheight) || isNaN(ajuploadwidth) || ajuploadheight === "" || ajuploadwidth === "") {
        // Fehler anzeigen, dass beide Felder ausgefüllt werden müssen
        const errorContainer = document.getElementById('aj-upload-error-container');
        showErrorMessage(errorContainer, "Bitte geben Sie sowohl Höhe als auch Breite an.");
        return; // Verhindert weitere Berechnungen
    }

    // Umwandeln in Zahlen (Fließkommazahlen)
    ajuploadheight = parseFloat(ajuploadheight);
    ajuploadwidth = parseFloat(ajuploadwidth);

    // Fehlerbehandlung
    let error = ""; // Variable für die Fehlermeldung

    // Fehlerbedingungen:
    if (ajuploadwidth > 70 && ajuploadheight > 70) {
        error = "Entweder die Breite darf maximal 70 cm betragen oder die Höhe maximal 70 cm.";
    } else if (ajuploadwidth > 70 && ajuploadheight > 200) {
        error = "Wenn die Breite mehr als 70 cm beträgt, darf die Höhe maximal 200 cm betragen.";
    } else if (ajuploadheight > 70 && ajuploadwidth > 200) {
        error = "Wenn die Höhe mehr als 70 cm beträgt, darf die Breite maximal 200 cm betragen.";
    } else if (ajuploadheight > 200) {
        error = "Die Höhe liegt über 200 cm.";
    } else if (ajuploadwidth > 200) {
        error = "Die Breite liegt über 200 cm.";
    } else if (ajuploadheight < 2){
        error = "Die Höhe sollte mindestens 2 cm betragen.";
    } else if (ajuploadwidth < 2){
        error = "Die Breite sollte mindestens 2 cm betragen.";
    }

    // Fehler anzeigen:
    const errorContainer = document.getElementById('aj-upload-error-container');

    if (error) {
        // Fehlernachricht im Container anzeigen
        showErrorMessage(errorContainer, error);
        return;
    } else {
        // Wenn kein Fehler, berechne den Preis:
        const heightInMeters = ajuploadheight / 100; // Höhe in Metern
        const widthInMeters = ajuploadwidth / 100; // Breite in Metern
        let area = ajUploadRound(heightInMeters * widthInMeters, 4); // Berechnung der Fläche in m²
        if (area == 0) {
            area = 0.001;
        }
        let finalPrice = ajUploadRound((area * ajuploadprice) + ajuploadextraprice, 2); // Endpreis
        const discountData = aj_upload_getDiscountedPrices(finalPrice, area);

        const quantityInput = document.querySelector("input.qty");
        const quantity = parseInt(quantityInput.value) || 1; // Eingabemenge, Standard 1
        let newPriceQm = finalPrice; // Standardpreis

        // Durchlaufe das Rabatt-Array
        discountData.forEach((discount) => {
            if (quantity >= discount.quantity) {
                newPriceQm = discount.endPrice; // Aktualisiere den Preis pro qm
            } else {
                return; // Breche die Schleife ab
            }
        });

        // Rabattierter Preis berechnen
        const price = ajUploadRound(newPriceQm * area, 2);

        // Preis anzeigen
        const ajuploadshowprice = document.getElementById('aj-upload-price');

if (quantity === 1) {
    ajuploadshowprice.innerHTML = `

            <strong>${finalPrice.toFixed(2).replace('.', ',')}&nbsp;€</strong>
    `;
} else {
    ajuploadshowprice.innerHTML = `
            <span style="text-decoration: line-through; color: #888;">
                ${finalPrice.toFixed(2).replace(".", ",")}&nbsp;€
            </span>
            <strong style="color: red; font-weight: bold; padding-left: 10px;">
                ${price.toFixed(2).replace(".", ",")}&nbsp;€
            </strong>
    `;
}


        aj_upload_createDiscountTable(finalPrice, area);
        // Fehler entfernen, wenn der Preis berechnet wurde
        clearErrorMessage(errorContainer);
    }
   // aj_upload_createDiscountTable(finalPrice, area);
}

function aj_upload_getDiscountedPrices(originalPrice, area) {
    const minPreis = 28; // Mindestpreis
    const productPrice = originalPrice / area; // Preis pro Quadratmeter
    const targetPrice = Math.max(productPrice * (1 - 94 / 100), minPreis);

    // Gesamtpreisreduktion
    const priceReduction = productPrice - targetPrice;

    // Definierte Stückzahlen
    const quantities = [1, 2, 5, 10, 20, 30, 50, 100, 250, 500, 1000];

    // Anzahl der Schritte
    const steps = quantities.length;

    // Rabatt pro Schritt
    const discountPerStep = priceReduction / (steps - 1);

    // Array für das Ergebnis
    const result = [];

    quantities.forEach((quantity, index) => {
        let discount = 0;
        let endPrice = productPrice;

        if (quantity >= 2) {
            // Berechnung des Rabatts für die aktuelle Menge
            discount = discountPerStep * index;
            endPrice = productPrice - discount;

            // Sicherstellen, dass der Rabatt den maximalen Rabatt nicht überschreitet
            if (endPrice < targetPrice) {
                endPrice = targetPrice;
                discount = productPrice - targetPrice;
            }
        }

        // Für Menge 1 gibt es keinen Rabatt
        if (quantity === 1) {
            discount = 0;
            endPrice = productPrice;
        }

        // Werte zum Ergebnis-Array hinzufügen
        result.push({
            quantity: quantity,
            discount: Math.round(discount * 100) / 100, // Auf 2 Dezimalstellen runden
            endPrice: endPrice,
        });
    });

    return result;
}

function aj_upload_createDiscountTable(originalPrice, area) {
    const rowsContainer = document.getElementById("aj-upload-discountRows");
    const toggleButton = document.getElementById("aj-upload-toggleButton");
  
    rowsContainer.innerHTML = "";
  
    // Hole die berechneten Preise aus der getDiscountedPrices-Funktion
    const discountsArray = aj_upload_getDiscountedPrices(originalPrice, area);
    const discounts = discountsArray.slice(1);
  
    // Anzahl der sichtbaren Zeilen
    const visibleRowsCount = 5;
  
    // Tabelle dynamisch erstellen
    discounts.forEach((discount, index) => {
      const row = document.createElement("div");
      row.classList.add("aj-upload-row");
  
      // Sichtbare oder versteckte Zeilen markieren
      if (index < visibleRowsCount) {
        row.classList.add("aj-upload-visible");
      } else {
        row.classList.add("aj-upload-hidden");
      }
  
      // Zellen erstellen
      const quantityCell = document.createElement("div");
      quantityCell.classList.add("aj-upload-cell");
      quantityCell.textContent = `Ab ${discount.quantity} Stück`;
  
      const priceCell = document.createElement("div");
      priceCell.classList.add("aj-upload-cell");
      priceCell.textContent = `${ajUploadRound(discount.endPrice * area, 2).toFixed(2).replace(".", ",")} €`;
  
      // Zellen in die Zeile einfügen
      row.appendChild(quantityCell);
      row.appendChild(priceCell);
  
      // Zeile in den Container einfügen
      rowsContainer.appendChild(row);
  
      // Separator hinzufügen
      if (index < discounts.length - 1) {
        const separator = document.createElement("hr");
        separator.classList.add("aj-upload-separator");
        if (index >= visibleRowsCount - 1) {
          separator.classList.add("aj-upload-hidden");
        }
        rowsContainer.appendChild(separator);
      }
    });
  
    // Button-Logik für Ein- und Ausblenden
    let isExpanded = false;
    toggleButton.addEventListener("click", (event) => {
      event.preventDefault();
      isExpanded = !isExpanded;
      const rows = document.querySelectorAll(".aj-upload-row");
      const separators = document.querySelectorAll(".aj-upload-separator");
  
      rows.forEach((row, index) => {
        if (index >= visibleRowsCount) {
          row.classList.toggle("aj-upload-hidden", !isExpanded);
        }
      });
  
      separators.forEach((separator, index) => {
        if (index >= visibleRowsCount - 1) {
          separator.classList.toggle("aj-upload-hidden", !isExpanded);
        }
      });
  
      toggleButton.textContent = isExpanded ? "Weniger anzeigen" : "Mehr anzeigen";
    });
  }

function aj_upload_inputchanger(){
    const quantityInput = document.querySelector("input.qty");
    quantityInput.addEventListener("input", function () {
      
      
      
        setTimeout(function () {
            aj_upload_calcPrice();
          
        }, 10);
      });
  
      const quantityButtons = document.querySelectorAll(
        ".quantity .plus, .quantity .minus"
      );
      quantityButtons.forEach((button) =>
        button.addEventListener("click", function () {
          
          setTimeout(function () {
            aj_upload_calcPrice();
          }, 10);
        })
      );
}

// Zeigt die Fehlermeldung im Container an
function showErrorMessage(container, message) {
    container.textContent = message;
    container.style.color = 'red';
    container.style.fontSize = '16px';
}

// Entfernt die Fehlermeldung
function clearErrorMessage(container) {
    container.textContent = '';
}

function ajUploadRound(value, precision = 0) {
    const factor = Math.pow(10, precision + 1); // Eine Stufe höher für präzisere Zwischenberechnung
    const tempValue = Math.round(value * factor) / 10; // Zwischenwert auf exakt eine Dezimalstelle mehr runden
    return Math.round(tempValue) / Math.pow(10, precision); // Endwert runden und zurückgeben
}





