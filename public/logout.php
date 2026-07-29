<?php
require __DIR__ . '/../src/bootstrap.php';
logout();
header('Location: login.php');
