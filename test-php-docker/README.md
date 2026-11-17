# test-php-docker

```bash
docker run --rm -p 8080:80 -v "$PWD:/app" -w /app php:8.5-cli php -S 0.0.0.0:80 -t . hello.php
```