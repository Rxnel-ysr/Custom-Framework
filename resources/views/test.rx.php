<?php

$data = ['yusron', 'ronel', 'rexarion'];
?>
<ul>
    @foreach($data as $i)
    <li>{{ $i }}</li>
    @endforeach

</ul>