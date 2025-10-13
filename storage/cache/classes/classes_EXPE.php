<?php
return array (
  'App\\EXPE\\Foundation\\CLI\\Command' => 
  array (
    'filepath' => 'App/Foundation/CLI/Command_EXPE.php',
    'depends' => 
    array (
    ),
    'init' => 
    array (
    ),
    'filemtime' => 1760277253,
  ),
  'App\\Test\\testClassWithInitAndDeps' => 
  array (
    'filepath' => 'test/testClassWithInitAndDeps.php',
    'depends' => 
    array (
    ),
    'init' => 
    array (
      0 => 'App\\Test\\say()',
      1 => 'App\\Test\\testClassWithInitAndDeps::init',
    ),
    'filemtime' => 1760367432,
  ),
);