FROM unit:1.34.2-php8.4

ARG UID=1000
ARG GID=1000
ENV UID=${UID}
ENV GID=${GID}

RUN addgroup --gid $GID test \
    && adduser --uid $UID --gid $GID --shell /bin/bash --home /home/test/ test

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    libicu-dev \
    libonig-dev \
    curl \
    wget \
    g++ \
    autoconf \
    build-essential \
    && docker-php-ext-install -j$(nproc) \
        pcntl \
        opcache \
        sockets \
        iconv \
        mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && ln -sf /dev/stdout /var/log/unit.log

# Composer
COPY --from=composer:2.8.5 /usr/bin/composer /usr/local/bin/composer

# Рабочая директория
WORKDIR /var/www/test
RUN chown -R test:test /var/www/test && chmod -R 777 /var/www/test

# PHP конфигурация
COPY ./php/*.ini /usr/local/etc/php/conf.d/

# Временная зона
ENV TZ=Europe/Moscow
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

EXPOSE 9003
