# PHP Load Balancer

A lightweight load balancer written in pure PHP using Swoole. Supports sticky sessions, round robin, least connections, and more.

## Features
- Sticky session support
- Multiple balancing strategies (round robin, random, least connections, etc.)
- Hot config reload
- Backend health checks
- Configurable access/error logging

## Requirements
- PHP 8.4
- Swoole

## Installation
```bash
git clone https://github.com/elliot-ghorbani/php-load-balancer.git
cd php-load-balancer
composer install
cp .env.example .env
php public/server.php
```

## License
MIT
