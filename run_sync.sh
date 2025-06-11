#!/bin/bash

REPO_PATH=$1
PHP_PATH=$2

cd "$REPO_PATH" && "$PHP_PATH" artisan schedule:run >> /dev/null 2>&1
