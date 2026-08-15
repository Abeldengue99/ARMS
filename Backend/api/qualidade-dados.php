<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

armsExigirPermissao($pdo, 'qualidade.ver', 'Não tem permissão para ver qualidade de dados.');

function armsQualidadeTodos(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function armsQualidadeContar(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function armsQualidadeLinhasEmFalta(PDO $pdo, string $condicao): array {
    return armsQualidadeTodos($pdo, "
        SELECT id, name, primary_email
        FROM arms.client
        WHERE is_active = TRUE
          AND {$condicao}
        ORDER BY name ASC
        LIMIT 20
    ");
}

function armsQualidadeNomes(array $linhas): string {
    $nomes = array_map(static function ($linha) {
        return trim((string)($linha['name'] ?? ''));
    }, $linhas);

    $nomes = array_values(array_filter($nomes, static fn($nome) => $nome !== ''));
    return implode(', ', array_slice($nomes, 0, 5));
}

function armsQualidadeResumo(PDO $pdo): array {
    $duplicadosNif = armsQualidadeTodos($pdo, "
        SELECT
            MIN(BTRIM(tax_id::text)) AS valor,
            COUNT(*)::int AS total,
            STRING_AGG(name, ', ' ORDER BY name) AS clientes
        FROM arms.client
        WHERE NULLIF(BTRIM(COALESCE(tax_id::text, '')), '') IS NOT NULL
        GROUP BY LOWER(BTRIM(tax_id::text))
        HAVING COUNT(*) > 1
        ORDER BY total DESC, valor ASC
        LIMIT 30
    ");

    $duplicadosEmail = armsQualidadeTodos($pdo, "
        SELECT
            LOWER(MIN(BTRIM(primary_email::text))) AS valor,
            COUNT(*)::int AS total,
            STRING_AGG(name, ', ' ORDER BY name) AS clientes
        FROM arms.client
        WHERE NULLIF(BTRIM(COALESCE(primary_email::text, '')), '') IS NOT NULL
        GROUP BY LOWER(BTRIM(primary_email::text))
        HAVING COUNT(*) > 1
        ORDER BY total DESC, valor ASC
        LIMIT 30
    ");

    $semNif = armsQualidadeContar($pdo, "
        SELECT COUNT(*)
        FROM arms.client
        WHERE is_active = TRUE
          AND NULLIF(BTRIM(COALESCE(tax_id::text, '')), '') IS NULL
    ");

    $semLocalizacao = armsQualidadeContar($pdo, "
        SELECT COUNT(*)
        FROM arms.client
        WHERE is_active = TRUE
          AND NULLIF(BTRIM(COALESCE(location, '')), '') IS NULL
    ");

    $semEmail = armsQualidadeContar($pdo, "
        SELECT COUNT(*)
        FROM arms.client
        WHERE is_active = TRUE
          AND NULLIF(BTRIM(COALESCE(primary_email::text, '')), '') IS NULL
    ");

    $semContacto = armsQualidadeContar($pdo, "
        SELECT COUNT(*)
        FROM arms.client c
        WHERE c.is_active = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM arms.client_contact cc
              WHERE cc.client_id = c.id
          )
    ");

    $linhasSemNif = armsQualidadeLinhasEmFalta($pdo, "NULLIF(BTRIM(COALESCE(tax_id::text, '')), '') IS NULL");
    $linhasSemLocalizacao = armsQualidadeLinhasEmFalta($pdo, "NULLIF(BTRIM(COALESCE(location, '')), '') IS NULL");
    $linhasSemEmail = armsQualidadeLinhasEmFalta($pdo, "NULLIF(BTRIM(COALESCE(primary_email::text, '')), '') IS NULL");
    $linhasSemContacto = armsQualidadeTodos($pdo, "
        SELECT c.id, c.name, c.primary_email
        FROM arms.client c
        WHERE c.is_active = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM arms.client_contact cc
              WHERE cc.client_id = c.id
          )
        ORDER BY c.name ASC
        LIMIT 20
    ");

    $alertas = [];

    foreach ($duplicadosNif as $grupo) {
        $alertas[] = [
            'tipo' => 'Duplicado',
            'campo' => 'NIF',
            'quantidade' => (int)$grupo['total'],
            'estado' => 'Rever',
            'detalhe' => 'O mesmo NIF aparece em mais de um cliente.',
            'exemplos' => $grupo['clientes'] ?? '',
            'valor' => $grupo['valor'] ?? '',
        ];
    }

    foreach ($duplicadosEmail as $grupo) {
        $alertas[] = [
            'tipo' => 'Duplicado',
            'campo' => 'E-mail',
            'quantidade' => (int)$grupo['total'],
            'estado' => 'Rever',
            'detalhe' => 'O mesmo e-mail principal aparece em mais de um cliente.',
            'exemplos' => $grupo['clientes'] ?? '',
            'valor' => $grupo['valor'] ?? '',
        ];
    }

    $faltas = [
        ['campo' => 'NIF', 'quantidade' => $semNif, 'linhas' => $linhasSemNif],
        ['campo' => 'Localização', 'quantidade' => $semLocalizacao, 'linhas' => $linhasSemLocalizacao],
        ['campo' => 'E-mail principal', 'quantidade' => $semEmail, 'linhas' => $linhasSemEmail],
        ['campo' => 'Contacto', 'quantidade' => $semContacto, 'linhas' => $linhasSemContacto],
    ];

    foreach ($faltas as $falta) {
        if ($falta['quantidade'] <= 0) {
            continue;
        }

        $alertas[] = [
            'tipo' => 'Em falta',
            'campo' => $falta['campo'],
            'quantidade' => (int)$falta['quantidade'],
            'estado' => 'Completar',
            'detalhe' => 'Cliente ativo com informação obrigatória em falta.',
            'exemplos' => armsQualidadeNomes($falta['linhas']),
            'valor' => '',
        ];
    }

    return [
        'resumo' => [
            'duplicados_nif' => count($duplicadosNif),
            'duplicados_email' => count($duplicadosEmail),
            'duplicados_total' => count($duplicadosNif) + count($duplicadosEmail),
            'sem_nif' => $semNif,
            'sem_localizacao' => $semLocalizacao,
            'sem_email' => $semEmail,
            'sem_contacto' => $semContacto,
            'incompletos_total' => $semNif + $semLocalizacao + $semEmail + $semContacto,
            'alertas_total' => count($alertas),
        ],
        'duplicados' => [
            'nif' => $duplicadosNif,
            'email' => $duplicadosEmail,
        ],
        'incompletos' => [
            'sem_nif' => $linhasSemNif,
            'sem_localizacao' => $linhasSemLocalizacao,
            'sem_email' => $linhasSemEmail,
            'sem_contacto' => $linhasSemContacto,
        ],
        'alertas' => $alertas,
    ];
}

$acao = $_GET['acao'] ?? 'resumo';

try {
    if ($acao !== 'resumo') {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => armsQualidadeResumo($pdo),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Não foi possível carregar a qualidade de dados.',
        'detalhe' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
