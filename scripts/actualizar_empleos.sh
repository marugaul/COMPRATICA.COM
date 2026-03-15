#!/bin/bash
# Script simple para actualizar empleos de Telegram
# Ejecutar: bash scripts/actualizar_empleos.sh

cd "$(dirname "$0")/.."
php scripts/import_telegram_jobs.php
