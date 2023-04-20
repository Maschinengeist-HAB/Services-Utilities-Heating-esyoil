FROM php:8.1-alpine

RUN docker-php-ext-configure pcntl --enable-pcntl  && docker-php-ext-install -j$(nproc) pcntl

LABEL org.opencontainers.image.source="https://github.com/Maschinengeist-HAB/Services-Utilities-Heating-esyoil"
LABEL org.opencontainers.image.description="esyOil Web API"
LABEL org.opencontainers.image.licenses="MIT"

COPY Service /opt/Service
COPY Library /opt/Library

VOLUME [ "/opt/Service" ]
WORKDIR "/opt/Service/"
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
CMD ["sh", "./Entry.sh"]