<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
armsExigirPermissao($pdo, ['clientes.ver', 'clientes.gerir'], 'Não tem permissão para listar clientes.');

try {
    $stmt = $pdo->query("
        SELECT 
            c.id, 
            c.name as company_name, 
            c.primary_email as contact_email, 
            c.tax_id,
            c.location,
            CASE WHEN c.is_active THEN 'ACTIVE' ELSE 'INACTIVE' END as status,
            (SELECT full_name FROM arms.client_contact cc WHERE cc.client_id = c.id LIMIT 1) as contact_name,
            (SELECT json_agg(json_build_object('nome', full_name, 'email', email, 'telefone', phone)) FROM arms.client_contact cc WHERE cc.client_id = c.id) as representantes
        FROM arms.client c
        ORDER BY c.name ASC
    ");
    
    $clientes = $stmt->fetchAll();
    
    // Fallback if contact_name, tax_id or location is null
    foreach ($clientes as &$c) {
        if (empty($c['tax_id'])) {
            $c['tax_id'] = '—';
        }
        if (empty($c['location'])) {
            $c['location'] = '—';
        }
        if (!$c['contact_name']) {
            $c['contact_name'] = 'Sem contacto';
        }
        
        // Descodificar JSON de representantes
        if (!empty($c['representantes'])) {
            $c['representantes'] = json_decode($c['representantes'], true);
        } else {
            $c['representantes'] = [];
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $clientes
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
