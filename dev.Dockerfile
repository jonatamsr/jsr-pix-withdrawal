# Local Dev Dockerfile
#
# @link     https://www.hyperf.io
# @document https://hyperf.wiki
# @contact  group@hyperf.io
# @license  https://github.com/hyperf/hyperf/blob/master/LICENSE

FROM hyperf/hyperf:8.4-alpine-v3.21-swoole
LABEL maintainer="Hyperf Developers <group@hyperf.io>" version="1.0" license="MIT" app.name="Hyperf"

##
# ---------- env settings ----------
##
# --build-arg timezone=UTC
ARG timezone
ARG UID=1000
ARG GID=1000

ENV TIMEZONE=${timezone:-"UTC"} \
    APP_ENV=dev \
    SCAN_CACHEABLE=(false)

# Make local user to avoid file permissions on runtime
RUN addgroup -g ${GID} application && \
    adduser -S -D -u ${UID} -G application -s /bin/ash -h /home/application application

# Install gRPC and protobuf extensions (required by open-telemetry/transport-grpc)
# Install Xdebug for debugging unit tests, and PCOV for code coverage
RUN apk add --no-cache php84-pecl-grpc php84-pecl-protobuf php84-pecl-xdebug php84-pecl-pcov

# update
RUN set -ex \
    # show php version and extensions
    && php -v \
    && php -m \
    && php --ri swoole \
    #  ---------- some config ----------
    && cd /etc/php* \
    # - config PHP
    && { \
        echo "upload_max_filesize=128M"; \
        echo "post_max_size=128M"; \
        echo "memory_limit=1G"; \
        echo "date.timezone=${TIMEZONE}"; \
        echo "grpc.enable_fork_support=1"; \
    } | tee conf.d/99_overrides.ini \
    # - config PCOV
    && { \
        echo "extension=pcov.so"; \
        echo "pcov.enabled=1"; \
        echo "pcov.directory=."; \
    } | tee conf.d/98_pcov.ini \
    # - config Xdebug (off by default, enabled on demand via XDEBUG_MODE env)
    && { \
        echo "zend_extension=xdebug.so"; \
        echo "xdebug.mode=off"; \
        echo "xdebug.client_host=host.docker.internal"; \
        echo "xdebug.client_port=9003"; \
        echo "xdebug.start_with_request=yes"; \
        echo "xdebug.idekey=VSCODE"; \
    } | tee conf.d/98_xdebug.ini \
    # - config gRPC fork support
    && { \
        echo "grpc.enable_fork_support=1"; \
    } | tee conf.d/97_grpc.ini \
    # - config timezone
    && ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone \
    # ---------- clear works ----------
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/man \
    && echo -e "\033[42;37m Build Completed :).\033[0m\n"

RUN chmod +x /usr/local/bin/composer

USER application

WORKDIR /opt/www

# Composer Cache
# COPY ./composer.* /opt/www/
# RUN composer install --no-dev --no-scripts

COPY . /opt/www
RUN composer install --no-scripts

EXPOSE 9501

ENTRYPOINT ["php", "/opt/www/bin/hyperf.php", "start"]
