
    document.addEventListener('DOMContentLoaded', function() {
        // Alle Bestell-Item-Container mit der Klasse "item" auswählen
        const orderItems = document.querySelectorAll('tr.item');

        // Für jedes Bestell-Item die ID aus dem data-Attribut holen und anzeigen
        orderItems.forEach(function(item) {
            const orderItemId = item.getAttribute('data-order_item_id'); // Die Bestell-Item-ID abrufen
            const itemName = item.querySelector('.name'); // Der Bereich, in dem der Artikelname angezeigt wird

            if (orderItemId && itemName) {
                // Die Bestell-Item-ID als Text hinzufügen
                const idDisplay = document.createElement('span');
                idDisplay.classList.add('order-item-id-display');
                idDisplay.textContent = ' (Item-ID: ' + orderItemId + ')';

                // Die ID nach dem Produktnamen anzeigen
                itemName.appendChild(idDisplay);
            }
        });
    });

