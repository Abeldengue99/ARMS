<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // For now, always fetch tenant 1 (Aksanti). 
    // In a fully developed SaaS, we'd look at the domain or the logged-in user's tenant_id.
    $tenantId = 1;

    $stmt = $pdo->prepare("
        SELECT system_name, primary_color, logo_url 
        FROM arms.tenant_settings 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();

    if (!$settings) {
        // Fallback defaults if not found
        $settings = [
            'system_name' => 'ARMS',
            'primary_color' => '#d97706',
            'logo_url' => 'img/logo.svg'
        ];
    } else {
        if (empty($settings['logo_url'])) {
            $settings['logo_url'] = 'img/logo.svg';
        }
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => $settings
    ]);

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno.']);
}
