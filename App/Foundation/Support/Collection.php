<?php

namespace App\Foundation\Support;

use RuntimeException;
use JsonSerializable;
use Countable;
use IteratorAggregate;
use ArrayAccess;
use stdClass;
use Traversable;

class Collection implements JsonSerializable, Countable, IteratorAggregate, ArrayAccess
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // ==================== CORE ====================

    public static function make(array $items = []): self
    {
        return new self($items);
    }

    public static function range($start, $end, $step = 1): self
    {
        return new self(range($start, $end, $step));
    }

    public static function times(int $number, ?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(range(1, $number));
        }

        return new self(array_map($callback, range(1, $number)));
    }

    public function all(): array
    {
        return $this->items;
    }

    public function toJson(int $options = 0, int $depth = 512): string
    {
        return json_encode($this->items, $options, $depth);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // ==================== FILTERING ====================

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function reject(callable $callback): self
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return reset($this->items);
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function firstOrFail(?callable $callback = null): mixed
    {
        $result = $this->first($callback);
        if ($result === null) {
            throw new RuntimeException("Item not found.");
        }
        return $result;
    }

    public function last(): mixed
    {
        return end($this->items);
    }

    public function where(string $key, $value): self
    {
        return $this->filter(fn($item) => $this->getValue($item, $key) == $value);
    }

    public function whereStrict(string $key, $value): self
    {
        return $this->filter(fn($item) => $this->getValue($item, $key) === $value);
    }

    public function whereIn(string $key, array $values): self
    {
        return $this->filter(fn($item) => in_array($this->getValue($item, $key), $values));
    }

    public function whereNotIn(string $key, array $values): self
    {
        return $this->reject(fn($item) => in_array($this->getValue($item, $key), $values));
    }

    public function whereBetween(string $key, array $range): self
    {
        return $this->filter(
            fn($item) =>
            $this->getValue($item, $key) >= $range[0] &&
                $this->getValue($item, $key) <= $range[1]
        );
    }

    public function whereNotBetween(string $key, array $range): self
    {
        return $this->reject(
            fn($item) =>
            $this->getValue($item, $key) >= $range[0] &&
                $this->getValue($item, $key) <= $range[1]
        );
    }

    public function whereNull(string $key): self
    {
        return $this->filter(fn($item) => is_null($this->getValue($item, $key)));
    }

    public function whereNotNull(string $key): self
    {
        return $this->filter(fn($item) => !is_null($this->getValue($item, $key)));
    }

    public function unique(?string $key = null): self
    {
        if ($key === null) {
            return new self(array_unique($this->items));
        }

        $unique = [];
        $result = [];

        foreach ($this->items as $item) {
            $value = $this->getValue($item, $key);
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
                $result[] = $item;
            }
        }

        return new self($result);
    }

    // ==================== TRANSFORMING ====================

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items, array_keys($this->items)));
    }

    public function mapInto(string $className): self
    {
        return $this->map(fn($item) => new $className($item));
    }

    public function transform(callable $callback): self
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    public function reduce(callable $fn, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $fn, $initial);
    }

    public function pluck(string $value, ?string $key = null): array
    {
        return array_column($this->items, $value, $key);
    }

    public function implode(string $glue): string
    {
        return implode($glue, $this->items);
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    public function tap(callable $callback): self
    {
        $callback(new self($this->items));
        return $this;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Convert the collection to an object of the specified class
     * 
     * @param string|null $className The class to instantiate (null for stdClass)
     * @param array $options Conversion options:
     *   - 'mode' => 'constructor' (default) - Pass items to constructor
     *   - 'mode' => 'property' - Set items as public properties
     *   - 'mode' => 'method:methodName' - Call method after instantiation
     *   - 'args' => [] - Additional constructor arguments
     * @return mixed
     */
    public function toObj(?string $className = null, array $options = []): mixed
    {
        $options = array_merge([
            'mode' => 'constructor',
            'args' => []
        ], $options);

        // Default to stdClass if no class specified
        if ($className === null) {
            return $this->convertToStdClass($options['deep'] ?? true);
        }

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        $modeParts = explode(':', $options['mode']);
        $mode = $modeParts[0];
        $method = $modeParts[1] ?? null;

        switch ($mode) {
            case 'constructor':
                return $this->instantiateViaConstructor($className, $options['args']);

            case 'property':
                return $this->instantiateAndSetProperties($className);

            case 'method':
                if ($method === null) {
                    throw new \InvalidArgumentException("Method name must be specified with method:mode");
                }
                return $this->instantiateAndCallMethod($className, $method, $options['args']);

            default:
                throw new \InvalidArgumentException("Invalid mode: {$mode}");
        }
    }

    protected function convertToStdClass(bool $deep = true): \stdClass
    {
        if (!$deep) {
            return (object)$this->items;
        }

        $object = new \stdClass();
        foreach ($this->items as $key => $value) {
            if (is_array($value)) {
                $object->$key = (new self($value))->toObj();
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    protected function instantiateViaConstructor(string $className, array $additionalArgs = []): object
    {
        $args = array_merge([$this->items], $additionalArgs);
        return new $className(...$args);
    }

    protected function instantiateAndSetProperties(string $className): object
    {
        $object = new $className();
        foreach ($this->items as $key => $value) {
            $object->$key = is_array($value)
                ? (new self($value))->toObj()
                : $value;
        }
        return $object;
    }

    protected function instantiateAndCallMethod(string $className, string $method, array $args = []): object
    {
        $object = new $className();

        if (!method_exists($object, $method)) {
            throw new \RuntimeException("Method {$method} does not exist on class {$className}");
        }

        $methodArgs = array_merge([$this->items], $args);
        
        return $object->$method(...$methodArgs);
    }

    // ==================== SORTING & GROUPING ====================

    public function groupBy(string $key): self
    {
        $grouped = [];
        foreach ($this->items as $item) {
            $groupKey = $this->getValue($item, $key);
            $grouped[$groupKey][] = $item;
        }
        return new self($grouped);
    }

    public function sortBy(string $key, bool $ascending = true): self
    {
        usort($this->items, function ($a, $b) use ($key, $ascending) {
            $valueA = $this->getValue($a, $key);
            $valueB = $this->getValue($b, $key);
            return $ascending ? $valueA <=> $valueB : $valueB <=> $valueA;
        });
        return $this;
    }

    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, false);
    }

    public function sortDesc(): self
    {
        rsort($this->items);
        return $this;
    }

    public function sortAsc(): self
    {
        sort($this->items);
        return $this;
    }

    public function reverse(): self
    {
        return new self(array_reverse($this->items, true));
    }

    public function shuffle(): self
    {
        shuffle($this->items);
        return $this;
    }

    // ==================== MUTATION ====================

    public function push(mixed $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function prepend(mixed $item): self
    {
        array_unshift($this->items, $item);
        return $this;
    }

    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    public function shift(): mixed
    {
        return array_shift($this->items);
    }

    public function merge(array|self $items): self
    {
        $items = $items instanceof self ? $items->all() : $items;
        return new self(array_merge($this->items, $items));
    }

    public function mergeRecursive(array|self $items): self
    {
        $items = $items instanceof self ? $items->all() : $items;
        return new self(array_merge_recursive($this->items, $items));
    }

    public function replace(array|self $items): self
    {
        $items = $items instanceof self ? $items->all() : $items;
        return new self(array_replace($this->items, $items));
    }

    public function chunk(int $size): self
    {
        $chunks = array_chunk($this->items, $size);
        return new self(array_map(fn($chunk) => new self($chunk), $chunks));
    }

    public function flatten(): self
    {
        $result = [];
        array_walk_recursive($this->items, function ($v) use (&$result) {
            $result[] = $v;
        });
        return new self($result);
    }

    public function flattenDeep(): self
    {
        $result = [];
        $stack = $this->items;

        while (!empty($stack)) {
            $current = array_shift($stack);

            if (is_array($current)) {
                array_unshift($stack, ...$current);
            } else {
                $result[] = $current;
            }
        }

        return new self($result);
    }

    public function collapse(): self
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new self($results);
    }

    public function zip(array|self $items): self
    {
        $items = $items instanceof self ? $items->all() : $items;
        $zipped = array_map(null, $this->items, $items);
        return new self($zipped);
    }

    // ==================== KEYED ACCESS ====================

    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(int|string $key, mixed $value): self
    {
        $this->items[$key] = $value;
        return $this;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function forget(int|string $key): self
    {
        unset($this->items[$key]);
        return $this;
    }

    public function only(array|string $keys): self
    {
        $keys = (array)$keys;
        $filtered = [];

        foreach ($this->items as $item) {
            if (is_array($item)) {
                $filtered[] = array_intersect_key($item, array_flip($keys));
            } elseif (is_object($item)) {
                $filteredItem = [];
                foreach ($keys as $key) {
                    if (property_exists($item, $key)) {
                        $filteredItem[$key] = $item->$key;
                    }
                }
                $filtered[] = $filteredItem;
            }
        }

        return new self($filtered);
    }

    public function except(array|string $keys): self
    {
        $keys = (array)$keys;
        $filtered = [];

        foreach ($this->items as $item) {
            if (is_array($item)) {
                $filtered[] = array_diff_key($item, array_flip($keys));
            } elseif (is_object($item)) {
                $filteredItem = [];
                foreach ($item as $key => $value) {
                    if (!in_array($key, $keys)) {
                        $filteredItem[$key] = $value;
                    }
                }
                $filtered[] = $filteredItem;
            }
        }

        return new self($filtered);
    }

    public function keyBy(string $key): self
    {
        $keyed = [];
        foreach ($this->items as $item) {
            $keyed[$this->getValue($item, $key)] = $item;
        }
        return new self($keyed);
    }

    public function keys(): self
    {
        return new self(array_keys($this->items));
    }

    public function values(): self
    {
        return new self(array_values($this->items));
    }

    // ==================== AGGREGATES ====================

    public function sum(?string $key = null): float|int
    {
        if ($key === null) {
            return array_sum($this->items);
        }

        return $this->reduce(fn($carry, $item) => $carry + $this->getValue($item, $key), 0);
    }

    public function avg(?string $key = null): float|int
    {
        $count = $this->count();
        if ($count === 0) {
            return 0;
        }

        return $this->sum($key) / $count;
    }

    public function min(?string $key = null): mixed
    {
        if ($key === null) {
            return min($this->items);
        }

        return $this->reduce(function ($carry, $item) use ($key) {
            $value = $this->getValue($item, $key);
            return $carry === null || $value < $carry ? $value : $carry;
        });
    }

    public function max(?string $key = null): mixed
    {
        if ($key === null) {
            return max($this->items);
        }

        return $this->reduce(function ($carry, $item) use ($key) {
            $value = $this->getValue($item, $key);
            return $carry === null || $value > $carry ? $value : $carry;
        });
    }

    public function median(?string $key = null): float|int
    {
        $values = $key ? $this->pluck($key) : $this->items;
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    public function mode(?string $key = null): array
    {
        $values = $key ? $this->pluck($key) : $this->items;
        $frequency = array_count_values($values);
        $maxFrequency = max($frequency);
        return array_keys($frequency, $maxFrequency);
    }

    // ==================== CONDITIONAL ====================

    public function contains(mixed $value): bool
    {
        if (is_callable($value)) {
            return $this->first($value) !== null;
        }

        return in_array($value, $this->items, true);
    }

    public function containsStrict(mixed $value): bool
    {
        return in_array($value, $this->items, true);
    }

    public function doesntContain(mixed $value): bool
    {
        return !$this->contains($value);
    }

    public function every(callable $callback): bool
    {
        foreach ($this->items as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }
        return true;
    }

    // ==================== HIGHER ORDER ====================

    public function higherOrderMap(): self
    {
        return $this->map(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item);
            };
        });
    }

    public function higherOrderFilter(): self
    {
        return $this->map(function ($item) {
            return function (callable $callback) use ($item) {
                return $callback($item) ? $item : null;
            };
        });
    }

    // ==================== UTILITY ====================

    private function getValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }
        if (is_object($item)) {
            return $item->$key ?? null;
        }
        throw new RuntimeException("Unsupported item type.");
    }

    public function dump(): self
    {
        Type::dump($this->items);
        return $this;
    }

    public function dd(): void
    {
        dd($this->items);
    }

    public function when(mixed $value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            return $callback($this);
        }

        if ($default) {
            return $default($this);
        }

        return $this;
    }

    public function unless(mixed $value, callable $callback, ?callable $default = null): self
    {
        return $this->when(!$value, $callback, $default);
    }

    public function macro(string $name, callable $macro): void
    {
        $this->{$name} = $macro->bindTo($this, self::class);
    }

    public function __call(string $method, array $parameters)
    {
        if (isset($this->{$method}) && is_callable($this->{$method})) {
            return call_user_func_array($this->{$method}, $parameters);
        }

        throw new RuntimeException("Method {$method} does not exist.");
    }
}
/**
 * 
 * Karya Ilmiah
 * ---
 * 
 * Angket, wawancara, literatur
 * 
 * Kerangka teori adalah buku-buku literatur yang berisi pendukung teori atau referenci
 * 
 * hipotesis, adalah kesimpulan awal, yang
 * pendahuluan: latar belakanag(alasan emngapa kalian meimilih judul)
 * 
 * abstrak, letak didepan, adalah ringkasan dari materi yang akan kamu bahas
 * 
 * biasanya kertas A4, font 12 Times New Roman, 1.5 margin
 * 
 * spasi line di asbtrak itu 1, tidak terlalu banyak hanya 2-3 paragrah
 * dan jika di pembahasan lainnya 1.5 jaraknya
 * 
 * penutup = kesimpulan keseluruhan + saran
 * 
 * 
 * daftar 
 * 
 * lampiran, isi: biodata, foto, tabel pembuatan karya ilmiah
 * 
 * 
 * 
 * 
 * kaidah 5:
 * * impoersonal: menyebutkan posisi bukan seperti saya, dll
 * * kata baku: harus effective, tidka boleh singkatan
 * * kalimat effective: kalimat tidak betele tele, simple tidak berlebihan dan logis dan tidak ambigu, "kelas sebelah naik keatas aula" -> tidak effektif karna naik itu pasti ke atas
 * * 
 * 
 * 
 * catatan kaki itu sama seperti daftar pustaka tapi bisa ad dimana saja,
 * ada biasanya jika ada kutipan pada suatu halaman
 * tapi juga harus ada di daftar pustaka
 * 
 * 
 * 
 * Daftar Pustaka
 * ---
 * * Nama pengarang
 * * Tahun terbit
 * * Judul buku
 * * Tempat/Kota
 * * Penerbit
 * 
 * Format:
 * ___
 * nama belakang, nama. tahun, *Judul Buku* \/ Judul buku tempat/kota: penerbit
 * ___
 * 
 * jika ada 2 pengarang, yang no 2 tidka diapa apakan
 * dan jika panjang maka tambahkan baris dan dorong ke dalam
 * jika bergelar maka gelar akan dihilangkan
 * 
 * jika pengarang lebih dari 2, cukup ditambah penulis utama namaAkhir, namaUtama. dkk.
 * 
 * catata kaki -> foot 
 * 
 *  Catatan Kaki
 * ---
 * * Nama pengarang
 * * Tahun terbit
 * * Judul buku
 * * Tempat/Kota
 * * Penerbit
 * * halaman
 * 
 * urutan 1 -> 5 -> 2 -> 3 -> 4 -> 6
 * 
 * nama pengarang utama tidak perlu di balik dan dipisahkan oleh koma `,` 
 * 
 * format
 * ___
 * nama, *judul buku* (Tempat/Kota: Penerbit, tahun terbit), hlm. halaman.
 * ___
 * 
 * jika 2 lebh:
 * Penerbi, dkk.,
 * 
 * ___Cara menulis Judul karangan___
 * 
 *      Pengaruh Penggunaan Pestisida
 * Terhadap Kualitas Tanah dan Air di Desa
 * 
 */
function bhsIndoKaryaIliah() {}
