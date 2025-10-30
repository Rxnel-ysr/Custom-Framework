<?php
#[Attribute]
class Inject
{
    public function __construct(public ?string $class = null) {}
}
