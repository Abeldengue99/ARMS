<?php
// Endpoint POST para criar um novo cliente no PostgreSQL
require_once 'db.php';
require_once 'auth.php';
require_once 'permissoes.php';

header('Content-Type: application/json; charset=utf-8');
armsExigirPermissao($pdo, 'clientes.gerir', 'Não tem permissão para criar clientes.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$nome = trim($input['nome'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$nif = trim($input['nif'] ?? '');
$localizacao = trim($input['localizacao'] ?? '');
$contactoNome = trim($input['contacto_nome'] ?? '');

// Validar campos obrigatórios
if ($nome === '' || $email === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Nome e Email são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido antes de criar o cliente.']);
    exit;
}

try {
    $stmtEmailDuplicado = $pdo->prepare("
        SELECT name
        FROM arms.client
        WHERE LOWER(BTRIM(primary_email::text)) = LOWER(:email)
        LIMIT 1
    ");
    $stmtEmailDuplicado->execute(['email' => $email]);
    $clienteEmailDuplicado = $stmtEmailDuplicado->fetch();

    if ($clienteEmailDuplicado) {
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Já existe um cliente com este e-mail principal: ' . $clienteEmailDuplicado['name']
        ]);
        exit;
    }

    if ($nif !== '') {
        $stmtNifDuplicado = $pdo->prepare("
            SELECT name
            FROM arms.client
            WHERE LOWER(BTRIM(tax_id::text)) = LOWER(:nif)
            LIMIT 1
        ");
        $stmtNifDuplicado->execute(['nif' => $nif]);
        $clienteNifDuplicado = $stmtNifDuplicado->fetch();

        if ($clienteNifDuplicado) {
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Já existe um cliente com este NIF: ' . $clienteNifDuplicado['name']
            ]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO arms.client (name, primary_email, tax_id, location)
        VALUES (:nome, :email, :nif, :localizacao)
        RETURNING id, name, primary_email, tax_id, location, is_active
    ");

    $stmt->execute([
        'nome'         => $nome,
        'email'        => $email,
        'nif'          => $nif !== '' ? $nif : null,
        'localizacao'  => $localizacao !== '' ? $localizacao : null
    ]);

    $novoCliente = $stmt->fetch();

    // Se houver contacto, inserir também
    if ($contactoNome !== '') {
        $stmtContacto = $pdo->prepare("
            INSERT INTO arms.client_contact (client_id, email, full_name)
            VALUES (:client_id, :email, :full_name)
            ON CONFLICT (client_id, email) DO UPDATE
            SET full_name = EXCLUDED.full_name
        ");
        $stmtContacto->execute([
            'client_id' => $novoCliente['id'],
            'email'     => $email,
            'full_name' => $contactoNome
        ]);
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Cliente criado com sucesso!',
        'cliente' => $novoCliente
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
