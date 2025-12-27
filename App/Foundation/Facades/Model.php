<?php

namespace App\Support\Facades;

use App\Foundation\Traits\Strings;
use App\Support\Facades\Facade;
use Dep;

/**
 * @method static \Experimental\App\Foundation\Database\QueryBuilder table($name)
 * @mixin \Experimental\App\Foundation\Database\QueryBuilder
 * @extends Facade<\Experimental\App\Foundation\Database\QueryBuilder>
 * 
 * @depends App\Support\Facades\Facade
*/
#[Dep(Facade::class)]
class Model extends Facade
{
    use Strings;

    protected static $table = null;
    protected static $primaryKey = 'id';
    protected static $fillable = [];
    protected static $guarded = [];
    
    protected static function getFacadeAccessor(): string|object
    {
        return \Experimental_V2\App\Foundation\Database\QueryBuilder::class;
    }
    
    public static function afterCreate($instance)
    {
        /** @var \Experimental_V2\App\Foundation\Database\QueryBuilder $instance  */
        if(!static::$table){
            $sanitized = strtolower(basename(str_replace('\\', '/', $instance::class)));
            $tableName = static::isPlural($sanitized) ? $sanitized : static::singularToPlural($sanitized);
        } else{
            $tableName = static::$table;
        }

        $instance->___table($tableName, static::$primaryKey);
        $instance->___fillable(static::$fillable);
        $instance->___guarded(self::$guarded);
    }

}
