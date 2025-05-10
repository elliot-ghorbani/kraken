![KrakenTide Logo](https://raw.githubusercontent.com/elliot-ghorbani/kraken_tide/main/.github/images/logo.jpg)
# KrakenTide, a PHP Swoole HTTP Load Balancer

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
git clone https://github.com/elliot-ghorbani/kraken-tide.git
cd kraken-tide
composer install
cp .env.example .env
php public/app.php
```

## License
MIT
