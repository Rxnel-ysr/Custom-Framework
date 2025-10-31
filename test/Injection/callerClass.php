<?php

namespace Test;

use Inject;

/**
 * Undocumented class
 * @depends ./TestClassDatabase.php
 * @depends ./TestClassLoger.php
 */
class Service
{
    public function __construct(
        #[Inject(Logger::class)] private Logger $log,
        #[Inject(Database::class)] private Database $db,
        public string $name = "default"
    ) {}

    public function run()
    {
        $this->db->connect();
        $this->log->log("Service {$this->name} started! type: {$this->log->type}");
    }
}
