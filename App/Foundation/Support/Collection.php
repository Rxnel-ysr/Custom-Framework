<?php

namespace App\Foundation\Support;

use RuntimeException;

class Collection
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // ==================== CORE METHODS ====================

    /** Get all items as an array. */
    public function all(): array
    {
        return $this->items;
    }

    /** Convert to JSON. */
    public function toJson(): string
    {
        return json_encode($this->items);
    }

    /** Count items. */
    public function count(): int
    {
        return count($this->items);
    }

    /** Check if empty. */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /** Check if not empty. */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    // ==================== FILTERING ====================

    /** Filter items by a callback. */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /** Reject items that pass a callback. */
    public function reject(callable $callback): self
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    /** Get first item matching condition (or null). */
    public function first(?callable $callback = null)
    {
        if ($callback === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    // ==================== MAPPING & TRANSFORMING ====================

    /** Transform each item with a callback. */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items, array_keys($this->items)));
    }

    /** Pluck values by key (optionally keyed). */
    public function pluck(string $value, ?string $key = null): array
    {
        return array_column($this->items, $value, $key);
    }

    /** Modify items in-place. */
    public function transform(callable $callback): self
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    // ==================== GROUPING & SORTING ====================

    /** Group items by a key. */
    public function groupBy(string $key): self
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $groupKey = $this->getValue($item, $key);
            $grouped[$groupKey][] = $item;
        }

        return new self($grouped);
    }

    /** Sort by key (ascending or descending). */
    public function sortBy(string $key, bool $ascending = true): self
    {
        usort($this->items, function ($a, $b) use ($key, $ascending) {
            $valueA = $this->getValue($a, $key);
            $valueB = $this->getValue($b, $key);

            return $ascending ? $valueA <=> $valueB : $valueB <=> $valueA;
        });

        return $this;
    }

    // ==================== KEY-VALUE OPERATIONS ====================

    /** Get only specified keys from each item. */
    public function only(array|string $keys): self
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

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

    /** Key collection by a field. */
    public function keyBy(string $key): self
    {
        $keyed = [];

        foreach ($this->items as $item) {
            $keyed[$this->getValue($item, $key)] = $item;
        }

        return new self($keyed);
    }

    // ==================== UTILITY METHODS ====================

    /** Get a value from an array or object. */
    private function getValue($item, string $key)
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        } elseif (is_object($item)) {
            return $item->$key ?? null;
        }

        throw new RuntimeException("Unsupported item type.");
    }

    /** Static constructor for fluent syntax. */
    public static function make(array $items = []): self
    {
        return new self($items);
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
