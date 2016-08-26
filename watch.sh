#!/usr/bin/env bash

MODULE_PATH="/modules/dividofinancing"

if [ -z "$1" ]; then
    echo "Usage: ./watch.sh <path_to_prestashop_root>"
    exit 1
fi

function updmod {
    date
    cp -R src/ $1
}

export -f updmod

TARGET_PATH="$1$MODULE_PATH"

fswatch -o src/ | xargs -n1 -I{} bash -c 'updmod "'"$TARGET_PATH"'"'

