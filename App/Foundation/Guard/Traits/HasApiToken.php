<?php

namespace App\Foundation\Guard\Traits;

use App\Foundation\Exceptions\Framework\HighLevelException;
use App\Foundation\Database\Model;

trait HasApiToken
{
    public function createToken()
    {
        /** @var Model $this */
        if ($this?->isFetched) {
            $token = ApiToken::from([
                'parent_id' => $this->{$this->getPrimary()},
                'class' => $this
            ]);
            return $token->save();
        } else {
            throw new HighLevelException("Model must be fetched before creating the token.");
        }
    }

    public function tokens()
    {
        return $this->hasMany(ApiToken::class);
    }
}

/**
 * Representation of bearer token
 * @method static self verifyToken(string $plain)
 */
class ApiToken extends Model
{
    protected $table = 'api_tokens';
    public Model $ownerModel;
    public string $plainTextToken;
    protected $timestamps = false;
    protected $fillable = [
        'id',
        'token',
        'parent_id',
        'class'
    ];

    protected function ___verifyToken(string $plain): ?static
    {
        [$id, $plainToken] = explode('|', $plain, 2);
        $hashed = hash('sha256', $plainToken);

        $token = $this->find($id);

        if (!$token || $token->token !== $hashed) {
            return null;
        }

        return $token;
    }

    public static function from($attributes = [])
    {
        $newToken = new self();
        $primary = $newToken->getPrimary();

        $newToken->ownerModel = $attributes['class'];
        $newToken->class = $attributes['class']::class;
        $newToken->parent_id = $attributes['parent_id'];
        $newToken->$primary = uuidv4();
        $gen = $newToken->genToken($newToken->$primary);
        $newToken->plainTextToken = $gen['plain'];
        $newToken->token =  $gen['hashed'];
        return $newToken;
    }

    protected function genToken(int|string $id)
    {
        $plaintext = bin2hex(random_bytes(40));
        $hashed    = hash('sha256', $plaintext);

        return [
            'plain'  => $id . '|' . $plaintext,
            'hashed' => $hashed,
        ];
    }

    public function owner()
    {
        return (new $this->class)->find($this->parent_id);
    }
}
