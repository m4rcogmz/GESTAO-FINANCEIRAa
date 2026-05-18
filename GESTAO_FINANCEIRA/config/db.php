<?php
// config/db.php
// Este ficheiro faz a ligação à base de dados MySQL usando PDO.
// Vamos usar uma função para obter a ligação sempre que precisarmos.

// 1. Dados de ligação - AJUSTAR se necessário ao teu XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestao_financeira');
define('DB_USER', 'root');      // utilizador por defeito no XAMPP
define('DB_PASS', '');          // normalmente vazio no XAMPP

/**
 * Devolve um objeto PDO ligado à base de dados.
 * Se houver erro, para a execução com uma mensagem simples.
 *
 * @return PDO
 */
function getPDO(): PDO
{
    static $pdo = null; // singleton simples: só cria uma ligação por pedido

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // erros em forma de exceção
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // resultados em arrays associativos
            ]);

            // Garante colunas/tabelas de admin e conta admin por defeito (idempotente)
            require_once __DIR__ . '/../includes/schema_bootstrap.php';
            bootstrap_finance_schema($pdo);
        } catch (PDOException $e) {
            // Em projeto real, não se deveria mostrar o erro completo ao utilizador
            die('Erro de ligação à base de dados: ' . $e->getMessage());
        }
    }

    return $pdo;
}


