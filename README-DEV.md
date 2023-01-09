Development on AnzuSystems Auth Bundle by Petit Press a.s. (www.sme.sk)
=====

Simple guide on how to develop on the project, run tests, etc.

---

# Installation

## 1. Clone the repository

    git clone https://github.com/anzusystems/auth-bundle.git

## 2. Start containers

Start project docker containers:

    bin/docker-compose up --build -d

Arguments:

- `--build` - Build all images to run fresh docker containers
- `-d` - Run docker containers in the detached mode as background processes

## 3. Build the application

Rebuild app from ground up:

    bin/build

# Scripts

Scripts available in the project.

## Bash

Script used to run bash inside the docker container:

    bin/bash

Execute `bin/bash -h` for all bash containers and options.

## Build

Script used to build the project.

    bin/build

Execute `bin/build -h` for all build options.

## Clear cache

Script used to clear all cache on local environment:

    bin/cc

Execute `bin/cc -h` for all options.

## Docker-compose script wrapper

Wrapper script used to run `docker-compose`:

    bin/docker-compose

Options:

- see [the official docker-compose docu][docker-compose-overview] for all options

Script will:

- setup correct permissions for the user if needed (sync UID and GID in docker container with host user, etc.)
- run `docker-compose` command

## ECS - Coding style fixer

Script used to run the coding style fixer:

    bin/ecs

## PSALM - Static analyses

Script used to run the static analyses:

    bin/psalm

## Security

Script used to run the security check inside the docker container:

    bin/security

## Test

Script used to run Unit tests inside the docker container:

    bin/test


[docker-compose-overview]: https://docs.docker.com/compose/reference/overview
