## Installation

**Make sure you have PHP 7.x+ and composer installed**

Then run:

```
git clone https://github.com/dimaip/calendar-backend
cd calendar-backend
composer install
```

## Usage

Run `./start.sh` or put inside proper PHP web server.

```
GET /day/20180318
GET /reading/%D0%91%D1%8B%D1%82.+XVII%2C+1-9.[&translation=1Aver]
```

## Run tests

Run from project root folder:

```
vendor/bin/phpunit tests
```
