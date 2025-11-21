#!/bin/bash
# Script de auto-deployment - SIN COMPOSER
# Ubicación: /home/comprati/public_html/deploy.sh
# NOTA: Composer deshabilitado porque no está en PATH del cron

cd /home/comprati/public_html || exit 1

# Git pull
git pull origin main

# Composer install DESHABILITADO
# PHPMailer ya está instalado en vendor/
# No ejecutar composer porque no está disponible en el cron
