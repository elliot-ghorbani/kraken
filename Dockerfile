FROM openswoole/swoole:25.2-php8.4

WORKDIR /var/kraken_tide

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts

COPY . .

EXPOSE 8080

CMD ["php", "public/app.php"]
