<?php

namespace App\Foundation\Database\Traits;

use App\Foundation\Database\Model;

trait HasUuid
{
    protected function uuidv4(): string
    {
        $b = random_bytes(16);

        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        $hex = bin2hex($b);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    protected function ___create(array $data): self
    {
        if(!isset($data[$this->getPrimary()])) $data[$this->getPrimary()] = uuidv4();
        if(in_array($this->getPrimary(), $this->fillable)) array_push($this->fillable, $this->getPrimary());
        return (clone $this)->___insert([$data]);
    }

    public function save()
    {
        /** @var Model $this */
        $currentTime = date('Y-m-d H:i:s');
        $array = $this->isFetched ? $this->dirty() : $this->all()->toArray();
        if ($this->isFetched) {
            if ($this->isDirty()) {
                (clone $this)->___where(
                    $this->primary,
                    $this->collectionData->get($this->primary)
                )->___update(
                    $this->timestamps ? array_merge($array, ['updated_at' => $currentTime]) : $array
                );
            }
        } else {
            if (isset($array[$this->getPrimary()])) {
                $array[$this->getPrimary()] = $this->uuidv4();
            }
            (clone $this)->___insert(
                $this->timestamps ? array_merge($array, ['updated_at' => $currentTime, 'created_at' => $currentTime]) : $array
            );
        }
    }
}
