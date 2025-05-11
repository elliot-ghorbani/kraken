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

## Configuration
Change the config.json file:
- worker_num: Number of workers | nullable | Default Value: number of cpu cores
- servers: Backend Servers | Array of Objects | Parameters: host, port, health_check_path (optional), ssl, weight
- strategy: Load Balancer Strategy | One of : random, least_conn, weighted_least_conn, round_robin, weighted_round_robin, least_response_time, weighted_least_response_time, sticky, ip_hash

## License
MIT
