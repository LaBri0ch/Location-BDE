<?php
declare(strict_types=1);
// Ancienne adresse de connexion du bureau : tout le monde se connecte maintenant par login.php.
require __DIR__ . '/../inc/bootstrap.php';
redirect('login.php');
