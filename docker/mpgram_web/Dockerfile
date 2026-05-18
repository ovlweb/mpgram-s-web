FROM phpdockerio/php:8.4-fpm

ARG UID
ARG LOTTIE_CONVERTER_VERSION=v1.2.0
ARG GIFSKI_VERSION=1.34.0

RUN apt-get update \
    && apt-get install -y gnupg curl ca-certificates unzip xz-utils \
    && curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ bullseye main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y php8.4-gd \
    && apt-get -y --no-install-recommends install \
    php8.4-mbstring \
    php8.4-gd \
    php8.4-ffi \
    php8.4-gmp \
    php8.4-iconv \
    php8.4-xml \
  # php8.4-json \ PHP8.3-JSON is not shipped as a separate component anymore
    php8.4-fileinfo \
    curl \
	patch \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY ./php.ini /etc/php/8.4/fpm/conf.d/90-php.ini
COPY ./php.ini /etc/php/8.4/cli/conf.d/90-php.ini

RUN curl -o /usr/local/etc/browscap.ini http://browscap.org/stream?q=Lite_PHP_BrowsCapINI

RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in amd64|arm64) lottie_arch="$arch" ;; *) echo "Unsupported lottie-converter arch: $arch" >&2; exit 1 ;; esac; \
    mkdir -p /opt/lottie; \
    curl -fsSL "https://github.com/ed-asriyan/lottie-converter/releases/download/${LOTTIE_CONVERTER_VERSION}/lottie-converter.linux.${lottie_arch}.zip" -o /tmp/lottie-converter.zip; \
    unzip -q /tmp/lottie-converter.zip -d /opt/lottie; \
    for f in /opt/lottie/bin/*.sh; do \
        if [ -f "$f" ] && ! head -n 1 "$f" | grep -q '^#!'; then sed -i '1i#!/usr/bin/env bash' "$f"; fi; \
        chmod 0755 "$f"; \
    done; \
    chmod 0755 /opt/lottie/bin/lottie_to_png; \
    curl -fsSL "https://github.com/ImageOptim/gifski/releases/download/${GIFSKI_VERSION}/gifski-${GIFSKI_VERSION}.tar.xz" -o /tmp/gifski.tar.xz; \
    tar -xJf /tmp/gifski.tar.xz -C /tmp; \
    install -m 0755 /tmp/linux/gifski /usr/local/bin/gifski; \
    rm -rf /tmp/lottie-converter.zip /tmp/gifski.tar.xz /tmp/linux /tmp/mac /tmp/win /tmp/LICENSE /tmp/README.md

RUN usermod -u ${UID} www-data

WORKDIR "/var/www/mpgram"

EXPOSE 9000
