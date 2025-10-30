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
    'boot' => 
    array (
    ),
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
    'boot' => 
    array (
    ),
  ),
  'App\\Foundation\\Event\\ReceiverInterface' => 
  array (
    'filepath' => 'App/Foundation/Event/EventInterfaces.php',
    'depends' => 
    array (
    ),
    'boot' => 
    array (
    ),
    'filemtime' => 1760611787,
  ),
  'Experimental\\App\\Foundation\\CLI\\Command' => 
  array (
    'filepath' => 'App/Foundation/CLI/Command_EXPE.php',
    'depends' => 
    array (
      0 => 'App\\Foundation\\Traits\\Macroable',
    ),
    'boot' => 
    array (
    ),
    'filemtime' => 1761554078,
  ),
);
