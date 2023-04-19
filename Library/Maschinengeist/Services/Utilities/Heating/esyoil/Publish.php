<?php
namespace Maschinengeist\Services\Utilities\Heating\esyoil;
use PhpMqtt\Client\MqttClient;


class Publish
{
    public function __construct(MqttClient $client, string $topic)
    {
        $this->topic    = $topic;
        $this->client   = $client;
    }

    public function connect() {
        if (false === $this->client->isConnected()) {
            $this->client->connect();
        }
    }

    public function publish(Seller $seller) {
        $this->connect();
        $topic = $this->topic;

        foreach ( $seller as $key => $value) {
            $this->client->publish("$topic/$key", $value);
        }
    }
}