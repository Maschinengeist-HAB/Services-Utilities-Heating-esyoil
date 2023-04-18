<?php
namespace Maschinengeist\Services\WWW\esyoil;

class Seller
{
    public function __construct(array $seller)
    {
        if (false === array_key_exists('brutto', $seller['pricing']['_100L'])) {
            throw new \Exception('Brutto price for 100L not found');
        }

        if (false === array_key_exists('brutto', $seller['pricing']['total'])) {
            throw new \Exception('Brutto price for total not found');
        }

        if (false === array_key_exists('name', $seller['dealer'])) {
            throw new \Exception('Seller name not found');
        }

        if (false === array_key_exists('date', $seller['delivery'])) {
            throw new \Exception('Delivery date not found');
        }

        if (false === array_key_exists('durationDays', $seller['delivery'])) {
            throw new \Exception('Delivery duration not found');
        }

        $this->l100         = $seller['pricing']['_100L']['brutto'];
        $this->total        = $seller['pricing']['total']['brutto'];
        $this->name         = $seller['dealer']['name'];
        $this->date         = $seller['delivery']['date'];
        $this->duration     = $seller['delivery']['durationDays'];
        $this->request_date = date('c');
    }
}