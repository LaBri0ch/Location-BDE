<?php
declare(strict_types=1);

// Réglages techniques. Les réglages du quotidien (liens HelloAsso/Swikly, e-mails...)
// se modifient depuis l'espace admin, pas ici.

define('APP_TZ', 'Europe/Paris');
define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('DB_FILE', DATA_DIR . '/location.sqlite');
define('INSTALL_LOCK', DATA_DIR . '/installed.lock');

// Mettre à true seulement pour chercher une erreur, puis remettre à false.
define('APP_DEBUG', false);
