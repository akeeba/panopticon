#!/usr/bin/env bash
docker compose down
#docker compose build --no-cache php
docker compose build php
docker compose up -d