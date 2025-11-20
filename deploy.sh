#!/bin/bash
# Script de auto-deployment con instalación de dependencias
# Ubicación: /home/comprati/public_html/deploy.sh

cd /home/comprati/public_html || exit 1

# Git pull
git pull origin main

# Composer install (solo si cambió composer.json)
if [ -f composer.json ]; then
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader --no-interaction
    fi
fi
