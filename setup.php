<?php
$dbPath = __DIR__ . '/storage/autofranquia.db';

if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql_create = "CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    nome TEXT NOT NULL, 
    email TEXT UNIQUE NOT NULL, 
    senha TEXT NOT NULL, 
    role TEXT NOT NULL, 
    franquia_id INTEGER, 
    ativo INTEGER DEFAULT 1, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$sql_franquias = "CREATE TABLE IF NOT EXISTS franquias (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    nome TEXT NOT NULL, 
    cidade TEXT NOT NULL, 
    cnpj TEXT UNIQUE NOT NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql_create);
    $pdo->exec($sql_franquias);
    echo "Tabelas criadas com sucesso!\n";
} catch (Exception $e) {
    echo "Erro ao criar tabelas: " . $e->getMessage() . "\n";
}

$franquias = [
    ['nome' => 'Matriz Porto Alegre', 'cidade' => 'Porto Alegre', 'cnpj' => '12.345.678/0001-90'],
    ['nome' => 'Filial Canoas', 'cidade' => 'Canoas', 'cnpj' => '12.345.678/0002-71'],
];

foreach ($franquias as $f) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO franquias (nome, cidade, cnpj) VALUES (?, ?, ?)");
    $stmt->execute([$f['nome'], $f['cidade'], $f['cnpj']]);
}
echo "Franquias criadas!\n";

$usuarios = [
    ['nome' => 'Admin Master', 'email' => 'admin@autofranquia.com', 'senha' => '123456', 'role' => 'super_admin', 'franquia_id' => NULL],
    ['nome' => 'Gerente Franquia', 'email' => 'gerente@franquia.com', 'senha' => '123456', 'role' => 'admin_franquia', 'franquia_id' => 1],
    ['nome' => 'Vendedor', 'email' => 'vendedor@franquia.com', 'senha' => '123456', 'role' => 'colaborador', 'franquia_id' => 1],
];

foreach ($usuarios as $u) {
    $hash = password_hash($u['senha'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO usuarios (nome, email, senha, role, franquia_id, ativo) VALUES (?, ?, ?, ?, ?, 1)");
    $result = $stmt->execute([$u['nome'], $u['email'], $hash, $u['role'], $u['franquia_id']]);
    if ($result) {
        echo "✓ Usuário criado: {$u['email']}\n";
    }
}

echo "\n✅ Setup concluído!\n";
echo "\nUsuários de teste:\n";
echo "Super Admin: admin@autofranquia.com / 123456\n";
echo "Gerente: gerente@franquia.com / 123456\n";
echo "Vendedor: vendedor@franquia.com / 123456\n";
?>