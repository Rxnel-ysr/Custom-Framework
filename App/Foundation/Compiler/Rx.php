<?php

$__sections = [];
$__currentSection = null;

function rx_start_section($name)
{
    global $__currentSection;
    $__currentSection = $name;
    ob_start();
}

function rx_end_section()
{
    global $__sections, $__currentSection;
    $__sections[$__currentSection] = ob_get_clean();
    $__currentSection = null;
}

function rx_yield($name)
{
    global $__sections;
    echo $__sections[$name] ?? '';
}

function rx_extends($parent)
{
    register_shutdown_function(function () use ($parent) {
        view($parent);
    });
}
