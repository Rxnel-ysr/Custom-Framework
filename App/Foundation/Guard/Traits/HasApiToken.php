<?php

namespace App\Foundation\Guard\Traits;

use App\Foundation\Exceptions\Framework\HighLevelException;
use App\Foundation\Database\Model;

trait HasApiToken
{
    public function createToken()
    {
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
    public $plainTextToken;
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

        $newToken->ownerModel = $attributes['class'];
        $attributes['class'] = $attributes['class']::class;
        $attributes['id'] = uuidv4();
        $gen = $newToken->genToken($attributes['id']);
        $newToken->plainTextToken = $gen['plain'];
        $attributes['token'] =  $gen['hashed'];
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
