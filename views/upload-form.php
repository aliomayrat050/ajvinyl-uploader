
<div class="upload-container" id="upload-container-item-<?= $itemID ?>">
    <h2>Datei für Artikel <?='Nr. '. $itemnr ?></h2>
    <p>Hier können Sie Datei für die Bestellung mit der Größe: <?=  $breite.' x '.$hoehe?> cm hochladen.</p>
    <p>Erlaubte Formate: svg, pdf, eps, ai </p>
    <div id="upload-area-item-<?= $itemID ?>" class="upload-area">
        Klicke hier oder ziehe eine Datei hinein, um sie hochzuladen.
        <input type="file" class="file-input" data-item-id="<?= $itemID ?>"  />
        <input type="hidden" name="orderid" class="orderid" value="<?= $orderID ?>">
        <input type="hidden" name="itemid" class="itemid" value="<?= $itemID ?>">
    </div>
    <div id="progress-bar-item-<?= $itemID ?>" class="progress-bar">
        <div class="progress"></div>
    </div>
    <ul id="upload-list-item-<?= $itemID ?>" class="upload-list"></ul>
</div>
