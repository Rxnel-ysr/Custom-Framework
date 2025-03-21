<?php

namespace App\Utils;

use App\Utils\Database\Connection;
use App\Utils\Database\QueryBuilder;

class Model extends QueryBuilder
{
    protected $table;
    protected $fillable = [];
    protected $guarded = [];
    protected $primary = 'id';
    private $isFetched;

    private $data = [];

    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->data[$key] = $value;
        }

        if (!$this->table) $this->table(strtolower(str_replace('base', '', basename(str_replace('\\', '/', get_called_class())))) . 's');

        $this->pdo = Connection::getInstance();
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function all()
    {
        return $this->data;
    }

    public function map($callback){
        return array_map($callback,$this->data);
    }

    public function filter($callback){
        return array_filter($this->data, $callback);
    }

    public function walk($callback, ...$args)
    {
        array_walk($this->data, $callback, ...$args);
        return $this;
    }

    public function getProp(){
        return $this->data;
    }

    public function save()
    {
        $this->isFetched ? $this->update($this->data) : $this->insert($this->data);
    }

    public function destroy()
    {

        array_map(fn($a,$b)=> $this->where($a,$b),$this->data);
        $this->delete();
    }
}

