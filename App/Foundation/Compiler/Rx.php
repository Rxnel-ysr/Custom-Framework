<?php

$__rx_sections = [];
$__rx_stacks = [];
$__rx_currentSection = null;
$__rx_currentStack = null;

$__rx_lastSection = null;
$__rx_lastStack = null;

$__sectionOpen = false;
$__stackOpen = false;

function rx_start_section($name)
{
    global $__rx_currentSection, $__sectionOpen;
    $__rx_currentSection = $name;
    $__sectionOpen = true;
    $__rx_lastSection = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    ob_start();
}

function rx_end_section()
{
    global $__rx_sections, $__rx_currentSection, $__sectionOpen;
    if (!$__rx_currentSection) throw new Exception('Unpaired closing section');
    $__rx_sections[$__rx_currentSection] = ob_get_clean();
    $__sectionOpen = false;
    $__rx_currentSection = null;
}

function rx_yield($name)
{
    global $__rx_sections, $__sectionOpen;
    if($__sectionOpen) throw new Exception("Unclosed section");
    echo $__rx_sections[$name] ?? '';
}

function rx_extends($parent)
{
    register_shutdown_function(function () use ($parent) {
        view($parent);
    });
}

function rx_append_stack($name)
{
    global $__rx_currentStack, $__stackOpen;
    $__rx_currentStack = $name;
    $__stackOpen = true;
    ob_start();
}

function rx_end_stack()
{
    global $__rx_stacks, $__rx_currentStack, $__stackOpen;
    if(!$__rx_currentStack) throw new Exception('Unpaired closing push');
    $__rx_stacks[$__rx_currentStack][] = ob_get_clean();
    $__stackOpen = false;
    $__rx_currentStack = null;
}

function rx_stacks($name)
{
    global $__rx_stacks, $__stackOpen;
    if($__stackOpen) throw new Exception("Unclosed push");
    foreach($__rx_stacks[$name] as $stack){
        echo $stack;
    };
    // var_dump($__rx_stacks);
}
