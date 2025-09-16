<?php

function getDatabase(): PDO {
    $host = 'localhost';
    $dbname = 'outdoor_logbook';
    $username = 'root';
    $password = '';

    $config = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    return new PDO($config, $username, $password);
}

?>