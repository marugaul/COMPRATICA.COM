<?php
/**
 * Configuración para importador de Telegram
 * Nota: El token no es necesario para scraping web de canales públicos
 */

// Token del bot de Telegram (opcional para canales públicos)
define('TELEGRAM_BOT_TOKEN', 'not_required_for_public_channels');

// Canales a importar (sin el @)
define('TELEGRAM_CHANNELS', [
    'STEMJobsCR',         // Empleos STEM en Costa Rica ⭐ Principal
    'STEMJobsLATAM',      // Empleos remotos LATAM ⭐ Principal
    'empleosti',          // Empleos TI Costa Rica
    'empleoscr506',       // Empleos Costa Rica general
    'remoteworkcr',       // Trabajo remoto CR
]);
