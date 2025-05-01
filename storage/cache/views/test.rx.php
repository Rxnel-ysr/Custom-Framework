<?php

$data = ['yusron', 'ronel', 'rexarion'];
?>
<ul>
    <?php foreach($data as $i): ?>
    <li><?= htmlspecialchars($i) ?></li>
    <?php endforeach;?>

</ul>