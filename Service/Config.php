<?php

namespace Maschinengeist\Services\Utilities\Heating\esyoil;

class Config {

    /**
     * Service version
     *
     * @return string
     */
    public static function getVersion() : string {
        return '2.0.0';
    }

    /**
     * MQTT Host
     * Set via environment variable MQTT_HOST
     *
     * @return string defaults to message-broker
     */
    public static function getMqttHost() : string {
        return $_ENV['MQTT_HOST'] ?? 'message-broker';
    }

    /**
     * MQTT Port
     * Set via environment variable MQTT_PORT
     *
     * @return int, defaults to 1883
     */
    public static function getMqttPort() : int {
        return (int) ($_ENV['MQTT_PORT'] ?? 1883);
    }

    /**
     * MQTT login username
     * Set via environment variable MQTT_USERNAME
     *
     * @return string, defaults to none
     */
    public static function getMqttUsername() : string {
        return $_ENV['MQTT_USERNAME'] ?? '';
    }

    /**
     * MQTT login password
     * Set via environment variable MQTT_PASSWORD
     *
     * @return string, defaults to none
     */
    public static function getMqttPassword() : string {
        return $_ENV['MQTT_PASSWORD'] ?? '';
    }

    # @Todo Fix keep alive param handling
    /**
     * MQTT keep alive in seconds
     * Set via environment variable MQTT_KEEP_ALIVE
     *
     * @return string, defaults to none
     */
    public static function getMqttKeepAlive() : bool {
        return $_ENV['MQTT_KEEP_ALIVE'] ?? true;
    }

    /**
     * MQTT base topic
     * Set via environment variable MQTT_BASE_TOPIC
     *
     * @return string defaults to maschinengeist/services/utilities/heating/esyoil
     */
    public static function getMqttBaseTopic() : string {
        return $_ENV['MQTT_BASE_TOPIC'] ?? 'maschinengeist/services/utilities/heating/esyoil';
    }

    /**
     * MQTT result topic
     *
     * @return string defaults to maschinengeist/services/utilities/heating/esyoil/results
     */
    public static function getMqttResultTopic() : string {
        return self::getMqttBaseTopic() . '/results';
    }

    /**
     * MQTT last will topic
     *
     * @return string defaults to maschinengeist/services/utilities/heating/esyoil/lwt
     */
    public static function getMqttLwtTopic() : string {
        return self::getMqttBaseTopic() . '/lwt';
    }

    /**
     * MQTT command topic
     *
     * @return string defaults to maschinengeist/services/utilities/heating/esyoil/command
     */
    public static function getMqttCommandTopic() : string {
        return self::getMqttBaseTopic() . '/command';
    }

    /**
     * esyoil, and thus this service, will not accept requests below this limit
     *
     * @return int, defaults to 500
     */
    public static function getDefaultMinQuoteLiters() : int {
        return 500;
    }

    /**
     * If no liters are requested, use this for the request.
     * Set via environment variable DEFAULT_REQUESTED_LITERS
     *
     * @return int defaults to 1000
     */
    public static function getDefaultRequestLiters() : int {
        return (int) ($_ENV['DEFAULT_REQUESTED_LITERS'] ?? 1000);
    }

    /**
     * If no zip cade is transmitted while requested, this is used
     * Set via environment variable DEFAULT_ZIPCODE
     *
     * @return string defaults to 33330
     */
    public static function getDefaultZipCode() : string {
        return $_ENV['DEFAULT_ZIPCODE'] ?? '33330';
    }

}