<?php
#[Attribute]
class Setup
{
    public function __construct(public array $args = [], public array $before = []) {}
}
