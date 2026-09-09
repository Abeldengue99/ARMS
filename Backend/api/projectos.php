<?php
// --- CABEÇALHOS CORS E SESSÃO OBRIGATÓRIOS ---
header("Access-Control-Allow-Origin: https://arms.support");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.client_id,
            c.name AS client_name,
            p.is_active,
            CASE WHEN p.is_active = TRUE THEN 'ACTIVE' ELSE 'INACTIVE' END AS status,
            p.created_at,
            COALESCE(members.member_names, '') AS member_names,
            COALESCE(members.member_ids, '[]') AS member_ids
        FROM arms.project p
        LEFT JOIN arms.client c ON p.client_id = c.id
        LEFT JOIN LATERAL (
            SELECT 
                string_agg(COALESCE(up.full_name, au.email), ', ' ORDER BY COALESCE(up.full_name, au.email)) AS member_names,
                json_agg(pm.user_id)::text AS member_ids
            FROM arms.project_member pm
            JOIN arms.auth_user au ON pm.user_id = au.id
            LEFT JOIN arms.user_profile up ON au.id = up.user_id
            WHERE pm.project_id = p.id
        ) members ON TRUE
        ORDER BY p.name ASC
    ");
    
    $projectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Converter member_ids de string JSON para array PHP
    foreach ($projectos as &$p) {
        $p['member_ids'] = json_decode($p['member_ids'] ?? '[]', true) ?: [];
    }
    unset($p);

    echo json_encode([
        'sucesso' => true,
        'dados' => $projectos
    ]);

} catch (Exception $e) {
    error_log('[ARMS] Erro ao carregar projetos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno ao carregar projetos.'
    ]);
}
?>
