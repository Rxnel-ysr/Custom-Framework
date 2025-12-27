<?php

namespace Test;

use App\Foundation\Manager\Resolver;
use Inject;
use Dep;

#[Dep('./TestClassDatabase.php')]
#[Dep('./TestClassLoger.php')]
/**
 * Undocumented class
 * @depends ./TestClassDatabase.php
 * @depends ./TestClassLoger.php
 */
class Service
{
    public function __construct(
        #[Inject(Logger::class, ['type' => 'Destrcution'])] private Logger $log,
        #[Inject(Database::class)] private Database $db,
        public string $name = "default"
    ) {}

    public function run()
    {
        $this->db->connect();
        $this->log->log("Service {$this->name} started! type: {$this->log->type}");
    }
}


$ress = Resolver::buildDefault([Logger::class, Database::class]);
$t = $ress[0];