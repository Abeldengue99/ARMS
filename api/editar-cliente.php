<?php
/**
 * ARMS — API para Editar Cliente
 * Atualiza dados de um cliente existente no PostgreSQL
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
armsExigirPermissao($pdo, 'clientes.gerir', 'Não tem permissão para editar clientes.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$id = trim($input['id'] ?? '');
$nome = trim($input['nome'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$nif = trim($input['nif'] ?? '');
$localizacao = trim($input['localizacao'] ?? '');
$contactoNome = trim($input['contacto_nome'] ?? '');

// Validar campos obrigatórios
if ($id === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do cliente é obrigatório.']);
    exit;
}

if ($nome === '' || $email === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome e Email são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido antes de atualizar o cliente.']);
    exit;
}

try {
    $stmtEmailDuplicado = $pdo->prepare("
        SELECT name
        FROM arms.client
        WHERE id <> CAST(:id AS uuid)
          AND LOWER(BTRIM(primary_email::text)) = LOWER(:email)
        LIMIT 1
    ");
    $stmtEmailDuplicado->execute([
        'id' => $id,
        'email' => $email
    ]);
    $clienteEmailDuplicado = $stmtEmailDuplicado->fetch();

    if ($clienteEmailDuplicado) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Já existe outro cliente com este e-mail principal: ' . $clienteEmailDuplicado['name']
        ]);
        exit;
    }

    if ($nif !== '') {
        $stmtNifDuplicado = $pdo->prepare("
            SELECT name
            FROM arms.client
            WHERE id <> CAST(:id AS uuid)
              AND LOWER(BTRIM(tax_id::text)) = LOWER(:nif)
            LIMIT 1
        ");
        $stmtNifDuplicado->execute([
            'id' => $id,
            'nif' => $nif
        ]);
        $clienteNifDuplicado = $stmtNifDuplicado->fetch();

        if ($clienteNifDuplicado) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Já existe outro cliente com este NIF: ' . $clienteNifDuplicado['name']
            ]);
            exit;
        }
    }

    $pdo->beginTransaction();

    // 1. Atualizar dados do cliente
    $stmt = $pdo->prepare("
        UPDATE arms.client 
        SET name = :nome, 
            primary_email = :email, 
            tax_id = :nif, 
            location = :localizacao,
            is_active = :ativo
        WHERE id = :id
        RETURNING id, name, primary_email, tax_id, location, is_active
    ");

    $stmt->execute([
        'id'           => $id,
        'nome'         => $nome,
        'email'        => $email,
        'nif'          => $nif !== '' ? $nif : null,
        'localizacao'  => $localizacao !== '' ? $localizacao : null,
        'ativo'        => isset($input['ativo']) ? ($input['ativo'] ? 'true' : 'false') : 'true'
    ]);

    $clienteAtualizado = $stmt->fetch();

    if (!$clienteAtualizado) {
        $pdo->rollBack();
        echo json_encode(['sucesso' => false, 'erro' => 'Cliente não encontrado.']);
        exit;
    }

    // 2. Atualizar ou criar contacto principal sem duplicar (client_id, email)
    if ($contactoNome !== '') {
        $stmtContacto = $pdo->prepare("
            INSERT INTO arms.client_contact (client_id, email, full_name)
            VALUES (:client_id, :email, :full_name)
            ON CONFLICT (client_id, email) DO UPDATE
            SET full_name = EXCLUDED.full_name
        ");
        $stmtContacto->execute([
            'client_id' => $id,
            'email'     => $email,
            'full_name' => $contactoNome
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'sucesso'  => true,
        'mensagem' => 'Cliente atualizado com sucesso!',
        'cliente'  => $clienteAtualizado
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'sucesso' => false,
        'erro'    => $e->getMessage()
    ]);
}
?>
