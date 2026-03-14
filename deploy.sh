#!/bin/bash

MODE=$1

case "$MODE" in

  api)
    echo "Starting API services..."
    docker compose up -d api db redis queue-worker
    ;;

  web)
    echo "Starting full stack (frontend + backend)..."
    docker compose up -d
    ;;

  stop)
    echo "Stopping all services..."
    docker compose stop
    ;;

  *)
    echo ""
    echo "Usage:"
    echo "./scripts/start.sh api   -> start API only (Android testing)"
    echo "./scripts/start.sh web   -> start full stack"
    echo "./scripts/start.sh stop  -> stop all running containers"
    echo ""
    ;;

esac