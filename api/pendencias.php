<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    armsAuthExigirAdmin();

    $pendencias = [];

    // 1. Convites pendentes (Contas ativas mas sem password alterada - utilizadores novos)
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.auth_user WHERE is_active = TRUE AND password_changed_at IS NULL");
    $pendencias['convites_pendentes'] = (int)$stmt->fetchColumn();

    // 2. Pedidos com deadline vencido
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.request WHERE deadline_at < NOW() AND status IN ('SENT', 'RECEIVED', 'CLIENT_RESPONDED')");
    $pendencias['pedidos_deadline_vencido'] = (int)$stmt->fetchColumn();

    // 3. Pedidos próximos do prazo (próximas 48 horas)
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.request WHERE deadline_at BETWEEN NOW() AND NOW() + INTERVAL '48 hours' AND status IN ('SENT', 'RECEIVED', 'CLIENT_RESPONDED')");
    $pendencias['pedidos_proximos_prazo'] = (int)$stmt->fetchColumn();

    // 4. Utilizadores desativados
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.auth_user WHERE is_active = FALSE");
    $pendencias['utilizadores_desativados'] = (int)$stmt->fetchColumn();

    // 5. Clientes com dados incompletos
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.client WHERE tax_id IS NULL OR tax_id = '' OR location IS NULL OR location = '' OR primary_email IS NULL OR primary_email = ''");
    $pendencias['clientes_dados_incompletos'] = (int)$stmt->fetchColumn();

    // 6. Falhas no envio de e-mail (alertas SMTP)
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.security_alert WHERE status = 'OPEN' AND message ILIKE '%SMTP%'");
    $pendencias['falhas_email'] = (int)$stmt->fetchColumn();

    // 7. Pedidos com alterações solicitadas (CLIENT_RESPONDED + decisao PENDING)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM arms.request r
        WHERE r.status = 'CLIENT_RESPONDED' 
        AND (
            SELECT decision 
            FROM arms.request_response rr 
            WHERE rr.request_id = r.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) = 'PENDING'
    ");
    $pendencias['pedidos_alteracoes'] = (int)$stmt->fetchColumn();

    // 8. Pedidos novos (Enviados pelos clientes e a aguardar análise)
    $stmt = $pdo->query("SELECT COUNT(*) FROM arms.request WHERE status = 'SENT'");
    $pendencias['pedidos_novos'] = (int)$stmt->fetchColumn();

    echo json_encode([
        'sucesso' => true,
        'pendencias' => $pendencias
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
