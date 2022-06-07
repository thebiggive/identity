# Identity service

Identity is a microservice and API (Application Programming Interface) used for handling everything to do with who's
who. For now the focus is on donors and making their journey smoother by avoiding the need to re-enter details.

Bootstrapped with [Slim Skeleton](https://github.com/slimphp/Slim-Skeleton), this PHP app uses Slim Framework 4
along with several other PHP libraries used across the Big Give.

* [Run the app](#Run-the-app)
* [Run unit tests](#Run-unit-tests)

## Run the app

You should usually use Docker to run the app locally in an easy way, with the least possible
configuration and the most consistency with other runtime environments - both those used
when the app is deployed 'for real' and other developers' machines.

### Prerequisites

In advance of the first app run:

* [get Docker](https://www.docker.com/get-started)
* copy `.env.example` to `.env` and change any values you need to.

### Start the app

To start the app and its dependency (`db`) locally:

    docker-compose up -d app

### First run

To get PHP dependencies and an initial data in structure in place, you'll need to run these once:

    docker-compose exec app composer install
    docker-compose exec app composer doctrine:delete-and-recreate

If dependencies change you may occasionally need to re-run the `composer install`.

### Once it's up

Check the `Status` endpoint works:

* [localhost:30050/ping](http://localhost:30050/ping)

## Run unit tests

Once you have the app running, you can test with:

    docker-compose exec app composer run test

When run with a coverage driver (e.g. Xdebug enabled by using `thebiggive/php:dev-8.1`),
this will save coverage data to `./coverage.xml`.

Linting is run with

    docker-compose exec app composer run lint:check

To understand how these commands are run in CI, see [the CircleCI config file](./.circleci/config.yml).
