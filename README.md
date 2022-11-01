# Identity service

Identity is a microservice and API (Application Programming Interface) used for handling everything to do with who's
who. For now the focus is on donors and making their journey smoother by avoiding the need to re-enter details.

Bootstrapped with [Slim Skeleton](https://github.com/slimphp/Slim-Skeleton), this PHP app uses Slim Framework 4
along with several other PHP libraries used across the Big Give.

* [Run the app](#Run-the-app)
* [Run unit tests](#Run-unit-tests)
* [API](#API)
* [Service dependencies](#Service-dependencies)
* [Scripts and Docker](#Scripts-and-Docker)
* [Code structure](#Code-structure)
* [Deployment](#Deployment)

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

## API

Actions are annotated with [swagger-php](https://github.com/zircote/swagger-php)-ready doc block annotations.

Generate OpenAPI documentation corresponding to your local codebase with:

    docker-compose exec app composer run docs

Once the app is more complete, we will copy/paste and publish generated docs to their
[live home on SwaggerHub](https://app.swaggerhub.com/apis/Noel/TBG-Identity/)
after any changes.

### Typical registration flow

So that new donors may have even their first, pre-registration donation associated with a
Stripe Customer, it's necessary for us to know a Stripe Customer ID as soon as we want a
Payment Intent. Because we take donation amount first, this means the Customer is essentially
anonymous on creation.

This means that registration when the donor decides to set a password typically has 3 important calls:

1. [Person\Create](./src/Application/Actions/Person/Create.php) (precedes all initiated donations)
2. [Person\Update](./src/Application/Actions/Person/Update.php) with no password (alongside all completed donations)
3. [Person\Update](./src/Application/Actions/Person/Update.php) with a password (when the donor sets one after donating)

## JWT types

Tokens can currently be issued with the following subject (`"sub"`) claims:

1. Some `"person_id"` and `"complete" false`: short-term (1 day) token permitting only updating a person's core details. Issued upon creation of a placeholder Person
  and permits setting identifying information and a password for them.
2. *Some `"person_id"` and `"complete" true`:* – 8-day token issued upon password authentication, allowing read + write access to everything for a
 complete Person record including saved payment methods. (This doesn't include full card numbers or data
 that would allow card use outside Big Give.)

## Service dependencies

It's expected that the a MySQL database will be a dependency. In live environments, MatchBot's RDS
database will be used (with a distinct schema) but this is not configured yet.

## Scripts and Docker

Scripts are defined in [`composer.json`](./composer.json). It's not currently anticipated
that any will be designed for Production use for this app, but there are scripts for testing
and linting, and many that may help with shortcuts to common Doctrine and database migration
developer tasks.

## Code structure

The Identity service's code is bootstrapped based on [Slim Skeleton](https://github.com/slimphp/Slim-Skeleton),
and elements like the error & shutdown handlers and much of the project structure follow its conventions.

Generally this structure follows normal conventions for a modern PHP app:

* Dependencies are defined (only) in `composer.json`, including PHP version and extensions
* Source code lives in [`src`](./src)
* PHPUnit tests live in [`tests`](./tests), at a path matching that of the class they cover in `src`
* Slim configuration logic and routing live in [`app`](./app)

### Configuration in `app`

* [`dependencies.php`](./app/dependencies.php): this sets up dependency
  injection (DI) for the whole app. This determines how every class gets most stuff it needs to run. DI is super
  powerful because of its flexibility (a class can say _I want a logger_ and not worry about which one), and typically
  avoids objects being created that aren't actually needed, or being created more times than needed. Both of these files
  work the same way - they are only separate for cleaner organisation.

  We use Slim's [PSR-11](https://www.php-fig.org/psr/psr-11/) compliant Container with [PHP-DI](http://php-di.org/).
  There's an [overview here](https://www.slimframework.com/docs/v4/concepts/di.html) of what this means in the context
  of Slim v4.

  With PHP-DI, we can also reduce some of our explicit depenendency definitions using [autowiring](http://php-di.org/doc/autowiring.html).
* [`repositories.php](./app/repositories.php): this sets up entity repositories that the app will use.
* [`routes.php`](./app/routes.php): this small file defines every route exposed on the web, and every authentication
  rule that applies to them. The latter is controlled by [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware and
  is very important to keep in the right place!

  Slim uses methods like `get(...)` and `put(...)` to hook up specific HTTP methods to classes that should be invoked.
  Our `Action`s' boilerplate is set up so that when the class is invoked, its `action(...)` method does the heavy
  lifting to serve the request.

  `add(...)` is responsible for adding middleware. It can apply to a single route or a whole group of them. Again, this
  is how we make routes authenticated. **Modify with caution!**
* [`settings.php`](./app/settings.php): you won't normally need to do much with this directly because it mostly just
  re-structures environment variables found in `.env` (locally) or env vars loaded from a secrets file (on ECS), into
  formats expected by classes we feed config arrays.

### Important code

The most important areas to explore in `src` are:

* [`Application\Actions`](./src/Application/Actions): all classes exposing APIs to the world. Anything invoked
  directly by a Route should be here.

## Deployment

Deploys are rolled out by [CirlceCI](https://circleci.com/), as [configured here](./.circleci/config.yml), to an
[ECS](https://aws.amazon.com/ecs/) cluster, where instances run the app live inside Docker containers.

As you can see in the configuration file,

* `develop` commits trigger deploys to staging and regression environments; and
* `main` commits trigger deploys to production

These branches are protected on GitHub and you should have a good reason for skipping any checks before merging to them!

### ECS runtime containers

ECS builds have two additional steps compared to a local run:

* during build, the [`Dockerfile`](./Dockerfile) adds the AWS CLI for S3 secrets access, pulls in the app files, tweaks
  temporary directory permissions and runs `composer install`. These things don't happen automatically with the [base
  PHP image](https://github.com/thebiggive/docker-php) as they don't usually make sense for local runs;
* during startup, the entrypoint scripts load in runtime secrets securely from S3 and ensure some cache directories have
  appropriate permissions. This is handled in the two `.sh` scripts in [`deploy`](./deploy) - one for web instances and
  one for tasks.

### Phased deploys

Other AWS infrastructure includes a load balancer, and ECS rolls out new app versions gradually to try and keep a
working version live even if a broken release is ever deployed. Because of this, new code may not reach all users until
about 30 minutes after CircleCI reports that a deploy is done. You can monitor this in the AWS Console.

When things are working correctly, any environment with at least two tasks in its ECS Service should get new app
versions with no downtime.
