FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        python3 \
        python3-pip \
        python3-venv \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip pcntl \
    && rm -rf /var/lib/apt/lists/*

RUN python3 -m venv /opt/chart-venv \
    && /opt/chart-venv/bin/pip install --no-cache-dir matplotlib==3.9.2

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/topic-signals

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

ENV CHART_PYTHON_BIN=/opt/chart-venv/bin/python
ENV CHART_SCRIPT_PATH=/var/www/topic-signals/docker/chart/render.py

CMD ["php-fpm"]
