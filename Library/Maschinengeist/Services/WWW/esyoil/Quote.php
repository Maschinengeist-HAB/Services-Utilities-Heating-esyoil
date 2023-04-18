<?php

namespace Maschinengeist\Services\WWW\esyoil;

class Quote
{
    public function __construct(string $zipcode, int $liters) {
        $this->setDefaults();

        if (false === preg_match('/^(?!01000|99999)(0[1-9]\d{3}|[1-9]\d{4})$/', $zipcode)) {
            throw new \Exception("$zipcode does not look like a valid german zip code");
        }

        if ($liters < 1) {
            throw new \Exception("$liters requested, but that's not enough for a request");
        }

        $this->zipcode = $zipcode;
        $this->liters  = $liters;
    }

    private function setDefaults() {
        $this->unloading_points = 1;
        $this->payment_type     = 'ec';
        $this->prod             = 'normal';
        $this->hose             = 'fortyMetre';
        $this->short_vehicle    = 'withTrailer';
        $this->deliveryTimes    = 'normal';
    }

    public function get() {
        $request_data = json_encode(array(
            'zipcode'           => $this->zipcode,
            'amount'            => $this->liters,
            'unloading_points'  => $this->unloading_points,
            'payment_type'      => $this->payment_type,
            'prod'              => $this->prod,
            'hose'              => $this->hose,
            'short_vehicle'     => $this->short_vehicle,
            'deliveryTimes'     => $this->deliveryTimes,
        ));

        $curl_handle = curl_init();

        curl_setopt_array($curl_handle, array(
            CURLOPT_URL             => 'https://backbone.esyoil.com/heating-oil-calculator/v1/calculate',
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      =>
                array(
                    'Content-type: application/json'
                ),
            CURLOPT_POSTFIELDS      => $request_data,
            CURLINFO_HEADER_OUT     => 1,
        ));

        $response = curl_exec($curl_handle);

        if (!$response) {
            throw new \Exception("Request to esyoil failed: ", print_r(curl_getinfo($curl_handle), true));
        }

        $seller = json_decode($response, true);
        return new Seller($seller['data'][0]);
    }
}