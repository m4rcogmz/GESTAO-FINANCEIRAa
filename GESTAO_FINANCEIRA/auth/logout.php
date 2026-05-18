<?php
// auth/logout.php
// Nesta fase apenas vamos destruir a sessão e voltar ao login.
// A sessão será criada mais tarde no sistema de autenticação.

session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;


