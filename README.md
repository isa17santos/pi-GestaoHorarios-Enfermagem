# Development Script

To simplify the development workflow, the project provides a helper script:

deploy.sh

This script abstracts the Docker Compose commands and allows starting or stopping the project services easily.

It supports different modes depending on the development scenario.

---

# Usage

## ./deploy.sh api

Starts only the backend services:
    api
    db
    redis
    queue-worker

## ./deploy.sh web [dev|build]

Starts all the services:
    frontend-web (runs in development mode by default, or build mode if 'build' or '--build' is specified)
    backend

## ./deploy.sh stop

Stops all runing containers 

# Don't forget

## Permissons

If you are running into some kind of trouble while executing the file it's much likely due to the fact that you did not run this one bellow: 
    chmod +x deploy.sh


