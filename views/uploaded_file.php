<?php

$status_color = '#E0A800';
$review_status_text = 'wird geprüft';

if ($review_status !== 'pending'){
$review_status_text = 'Akzeptiert';
$status_color = '#4CAF50';
}

?>

<div class='uploaded-file'>
                        <span class='file-name'>Maße: <?=  $breite.' x '.$hoehe?> cm</span>
                        <span class='file-name'><?= $original_filename ?></span>
                        <span class='status' style="color: <?= $status_color ?>;"><?= $review_status_text ?></span>
                        
                    </div>