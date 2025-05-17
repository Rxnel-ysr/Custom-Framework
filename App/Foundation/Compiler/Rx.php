<?php

$__sections = [];
$__stacks = [];
$__currentSection = null;
$__currentStack = null;

$__sectionOpen = false;
$__stackOpen = false;

function rx_start_section($name)
{
    global $__currentSection, $__sectionOpen;
    $__currentSection = $name;
    $__sectionOpen = true;
    ob_start();
}

function rx_end_section()
{
    global $__sections, $__currentSection, $__sectionOpen;
    if (!$__currentSection) throw new Exception('Unpaired closing section');
    $__sections[$__currentSection] = ob_get_clean();
    $__sectionOpen = false;
    $__currentSection = null;
}

function rx_yield($name)
{
    global $__sections, $__sectionOpen;
    if($__sectionOpen) throw new Exception("Unclosed section");
    echo $__sections[$name] ?? '';
}

function rx_extends($parent)
{
    register_shutdown_function(function () use ($parent) {
        view($parent);
    });
}

function rx_append_stack($name)
{
    global $__currentStack, $__stackOpen;
    $__currentStack = $name;
    $__stackOpen = true;
    ob_start();
}

function rx_end_stack()
{
    global $__stacks, $__currentStack, $__stackOpen;
    if(!$__currentStack) throw new Exception('Unpaired closing push');
    $__stacks[$__currentStack][] = ob_get_clean();
    $__stackOpen = false;
    $__currentStack = null;
}

function rx_stacks($name)
{
    global $__stacks, $__stackOpen;
    if($__stackOpen) throw new Exception("Unclosed push");
    foreach($__stacks[$name] as $stack){
        echo $stack;
    };
    // var_dump($__stacks);
}
