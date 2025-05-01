<?php

return  [
    function () {
        if (isset($_REQUEST['csrf_']) || isset($_REQUEST['csrf_key'])) {
            App\Foundation\Guard\CSRF::validateCSRF();
        }
    }
];
