<?php
/**
 * platform_settings.php
 * Leitura da configuração global (nome do site, logo) para usar no layout.
 */

declare(strict_types=1);

/**
 * @return array{nome_site: string, logo_ficheiro: ?string}
 */
function plataforma_obter_config(PDO $pdo): array
{
    if (!function_exists('schema_table_exists')) {
        require_once __DIR__ . '/schema_bootstrap.php';
    }
    if (!schema_table_exists($pdo, 'plataforma_config')) {
        return ['nome_site' => 'Gestão Financeira Familiar', 'logo_ficheiro' => null];
    }

    $st = $pdo->query('SELECT nome_site, logo_ficheiro FROM plataforma_config WHERE id = 1 LIMIT 1');
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    if (!$row) {
        return ['nome_site' => 'Gestão Financeira Familiar', 'logo_ficheiro' => null];
    }

    return [
        'nome_site' => (string)($row['nome_site'] ?? 'Gestão Financeira Familiar'),
        'logo_ficheiro' => $row['logo_ficheiro'] !== null && $row['logo_ficheiro'] !== ''
            ? (string)$row['logo_ficheiro']
            : null,
    ];
}

/**
 * URL relativa ao site (para <img src> a partir de pages/ ou admin/).
 */
function plataforma_logo_url(?string $logoFicheiro, string $prefixoRelativo): string
{
    if ($logoFicheiro === null || $logoFicheiro === '') {
        return '';
    }

    return $prefixoRelativo . 'assets/img/platform/' . rawurlencode($logoFicheiro);
}
