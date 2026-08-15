<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'acesso-pedidos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

armsAuthExigirLogin();

try {
    armsPedidosGarantirDestinoInterno($pdo);

    $userType = $_SESSION['arms_user_type'] ?? 'AKSANTI';
    $clientId = $_SESSION['arms_client_id'] ?? null;
    $isAdmin = armsAuthBool($_SESSION['arms_is_admin'] ?? false);
    $modoCliente = $userType === 'CLIENT';
    $modoColaborador = $userType !== 'CLIENT' && !$isAdmin;
    $userId = $_SESSION['arms_user_id'] ?? null;

    $stmtAreas = $pdo->query("SELECT id, code, name FROM arms.area ORDER BY name ASC");
    $areas = $stmtAreas->fetchAll();

    if ($modoCliente) {
        if (!$clientId) {
            echo json_encode(['sucesso' => false, 'erro' => 'Esta conta de cliente não está associada a uma empresa.']);
            exit;
        }

        $stmtClientes = $pdo->prepare("SELECT id, name, primary_email FROM arms.client WHERE id = :id AND is_active = TRUE");
        $stmtClientes->execute([':id' => $clientId]);
        $cliente = $stmtClientes->fetch();

        if (!$cliente) {
            echo json_encode(['sucesso' => false, 'erro' => 'Empresa associada não encontrada ou inativa.']);
            exit;
        }

        $clientes = [$cliente];
    } elseif ($modoColaborador) {
        $clientes = [armsPedidosClienteInternoAksanti($pdo)];
    } else {
        $stmtClientes = $pdo->query("
            SELECT id, name, primary_email
            FROM arms.client
            WHERE is_active = TRUE
            ORDER BY name ASC
        ");
        $clientes = $stmtClientes->fetchAll();
    }

    $membrosAksanti = [];
    if ($isAdmin) {
        $stmtMembros = $pdo->prepare("
            SELECT
                au.id,
                au.email,
                COALESCE(up.full_name, au.email) AS full_name,
                COALESCE(up.phone, '') AS cargo,
                COALESCE(au.is_admin, FALSE) AS is_admin,
                COALESCE(areas.area_ids, '[]'::json) AS area_ids
            FROM arms.auth_user au
            LEFT JOIN arms.user_profile up ON up.user_id = au.id
            LEFT JOIN LATERAL (
                SELECT json_agg(area_id) AS area_ids
                FROM arms.area_membership
                WHERE user_id = au.id
            ) areas ON TRUE
            WHERE au.user_type = 'AKSANTI'
              AND au.is_active = TRUE
              AND au.id <> :user_id
            ORDER BY COALESCE(au.is_admin, FALSE) DESC, COALESCE(up.full_name, au.email) ASC
        ");
        $stmtMembros->execute([':user_id' => $userId]);
        $membrosAksantiDb = $stmtMembros->fetchAll();
        
        foreach ($membrosAksantiDb as $m) {
            $m['area_ids'] = json_decode($m['area_ids'] ?? '[]');
            $membrosAksanti[] = $m;
        }
    }

    echo json_encode([
        'sucesso' => true,
        'modo_cliente' => $modoCliente,
        'modo_colaborador' => $modoColaborador,
        'modo_admin' => $isAdmin,
        'parceiro' => [
            'nome' => 'Aksanti',
            'email' => 'geral@aksanti.xyz'
        ],
        'areas' => $areas,
        'clientes' => $clientes,
        'membros_aksanti' => $membrosAksanti
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
