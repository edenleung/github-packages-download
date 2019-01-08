<?php
require './src/Reptile.php';
$server = new Reptile('https://github.com/facebook/react');
$server->start();