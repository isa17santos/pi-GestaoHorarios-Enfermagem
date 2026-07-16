#!/bin/bash

MODE=$1

case "$MODE" in

  api)
    echo "Starting API services..."
    docker compose up -d api db redis queue-worker
    ;;

  web)
    FRONTEND_MODE="dev"
    if [ "$2" = "build" ] || [ "$2" = "--build" ]; then
      FRONTEND_MODE="build"
    fi

    if [ "$FRONTEND_MODE" = "build" ]; then
      echo "Starting full stack (frontend in BUILD/PROD mode)..."
      FRONTEND_CMD="npm install && npm run build && npm run preview" docker compose up -d
    else
      echo "Starting full stack (frontend in DEV mode)..."
      FRONTEND_CMD="npm install && npm run dev" docker compose up -d
    fi
    ;;

  stop)
    echo "Stopping all services..."
    docker compose stop
    ;;

  *)
    echo ""
    echo "Usage:"
    echo "./deploy.sh api             -> start API only (Android testing)"
    echo "./deploy.sh web [dev|build] -> start full stack (frontend in dev or build mode, default: dev)"
    echo "./deploy.sh stop            -> stop all running containers"
    echo ""
    ;;

esac