# Services-WWW-esyoil
Access esyoil web api via MQTT messages

## Configuration
The service uses a set of environment variables for configuration in the Dockerfile:

### Connection settings

| Variable          | Usage                                                                          | Default value                        |
|-------------------|--------------------------------------------------------------------------------|--------------------------------------|
| `MQTT_HOST`       | Specifies the MQTT broker host name                                            | `message-broker`                     |
| `MQTT_PORT`       | Specifies the MQTT port                                                        | `1883`                               |
| `MQTT_RETAIN`     | Retain messages or not                                                         | `1` (retain)                         |
| `MQTT_KEEP_ALIVE` | Keep alive the connection to the MQTT broker every *n* seconds                 | `120`                                |
| `MQTT_BASE_TOPIC` | MQTT base topic, will prepend to the defined topics, i.e. `base_topic/command` | `maschinengeist/services/www/esyoil` |
| `TZ`              | Timezone                                                                       | `Europe/Berlin`                      |

### Last will and testament
The service will publish it's current connection state to `base_topic/lwt`.

## How to pull and run this image
Pull this image by

    docker pull ghcr.io/maschinengeist-hab/services-www-esyoil:latest

Run this image by

    docker run -d --name esyoil-service ghcr.io/maschinengeist-hab/services-www-esyoil:latest

## Command examples
### Get a quote
Publish
    
    {
        "command": "get-quote",
        "quote": {
            "zip": "33330",
            "requested-liters": 2000
        }
    }

to `base_topic/command` for requesting a quote for 2000 liters, deliverable to 33330 DE.

## License

    Copyright 2023 Christoph 'knurd' Morrison

    Licensed under the MIT license:

    http://www.opensource.org/licenses/mit-license.php

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.