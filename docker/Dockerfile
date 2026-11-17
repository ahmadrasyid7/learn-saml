FROM php:8.5-cli

# dom, libxml, openssl, zlib and session are already compiled into the base image.
# unzip is needed by Composer to extract downloaded packages.
# bcmath is required by simplesamlphp/xml-common (used by simplesamlphp/saml2 v6).
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install bcmath
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
# Passing public/index.php as the router sends every request through Symfony.
CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "public/index.php"]
