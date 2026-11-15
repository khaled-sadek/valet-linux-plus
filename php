#!/bin/bash

set -e

DEFAULT_PHP_BINARY="/usr/bin/php"
CONFIG_FILE="$HOME/.config/valet/config.json"
SELECTED_PHP=$DEFAULT_PHP_BINARY

# Check if the JSON file exists
if [ -f "$CONFIG_FILE" ]; then
    FALLBACK_PHP=$(jq -r '.fallback_binary // empty' "$CONFIG_FILE")
    if [ -n "$FALLBACK_PHP" ] && [ "$FALLBACK_PHP" != "null" ]; then
        SELECTED_PHP="$FALLBACK_PHP"
    fi

    IS_PWD_MATCHED=false
    SITE_NAME=""

    # Read the paths array from the JSON file
    while IFS= read -r path; do
        path="${path%/}"
        if [[ -n "$path" && ("$PWD" == "$path" || "$PWD" == "$path/"*) ]]; then
            IS_PWD_MATCHED=true
            SITE_NAME=$(basename "$PWD")
            break
        fi
    done < <(jq -r '(.paths // []) | .[]' "$CONFIG_FILE")

    SITES_DIR="$HOME/.config/valet/Sites"
    if [ -d "$SITES_DIR" ]; then
        for linkname in "$SITES_DIR"/*; do
            if [ -L "$linkname" ]; then
                TARGET=$(readlink -f "$linkname" || true)
                TARGET="${TARGET%/}"
                if [[ -n "$TARGET" && ("$PWD" == "$TARGET" || "$PWD" == "$TARGET/"*) ]]; then
                    LINK_SITE_NAME="${linkname##*/}"
                    if [ "$IS_PWD_MATCHED" = false ] || [ -n "$(jq -r --arg site "$LINK_SITE_NAME" '(.isolated_versions | objects[$site]) // empty' "$CONFIG_FILE")" ]; then
                        IS_PWD_MATCHED=true
                        SITE_NAME="$LINK_SITE_NAME"
                        break
                    fi
                fi
            fi
        done
    fi

    if [ "$IS_PWD_MATCHED" = true ]; then
        ISOLATED_PHP=$(jq -r --arg site "$SITE_NAME" '(.isolated_versions | objects[$site]) // empty' "$CONFIG_FILE")
        if [ -n "$ISOLATED_PHP" ] && [ "$ISOLATED_PHP" != "null" ]; then
            SELECTED_PHP="$ISOLATED_PHP"
        fi
    fi
fi

if ! [ -f "$SELECTED_PHP" ] || [ "$(readlink -f "$SELECTED_PHP")" = "$(readlink -f "${BASH_SOURCE[0]}")" ]; then
    SELECTED_PHP=$DEFAULT_PHP_BINARY
fi

# shellcheck disable=SC2145
eval "$SELECTED_PHP ${@@Q}"
