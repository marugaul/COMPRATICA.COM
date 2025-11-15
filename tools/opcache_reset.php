<?php
if (function_exists('opcache_reset')) {
  opcache_reset();
  echo "OPcache limpio.";
} else {
  echo "OPcache no disponible.";
}
