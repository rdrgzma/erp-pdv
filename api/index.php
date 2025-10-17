<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbPath = __DIR__ . '/../storage/autofranquia.db';

if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]));
}

$sql_init = array(
    "CREATE TABLE IF NOT EXISTS produtos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, sku TEXT UNIQUE NOT NULL, categoria TEXT NOT NULL, preco REAL NOT NULL, estoque INTEGER NOT NULL, imagem TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, tipoDocumento TEXT NOT NULL, documento TEXT, email TEXT, telefone TEXT, veiculo TEXT, logradouro TEXT, bairro TEXT, cep TEXT, cidade TEXT, uf TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS vendas (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente TEXT NOT NULL, valor REAL NOT NULL, data DATE NOT NULL, franquia_id INTEGER NOT NULL, pagamento TEXT, desconto REAL DEFAULT 0, acrescimo REAL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS venda_itens (id INTEGER PRIMARY KEY AUTOINCREMENT, venda_id INTEGER NOT NULL, produto_id INTEGER NOT NULL, quantidade INTEGER NOT NULL, preco_unitario REAL NOT NULL, FOREIGN KEY (venda_id) REFERENCES vendas(id), FOREIGN KEY (produto_id) REFERENCES produtos(id))",
    "CREATE TABLE IF NOT EXISTS financeiro (id INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT NOT NULL, valor REAL NOT NULL, descricao TEXT NOT NULL, data DATE NOT NULL, franquia_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS franquias (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, cidade TEXT NOT NULL, cnpj TEXT UNIQUE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
);

foreach ($sql_init as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
    }
}

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/index.php', '', $path);
$path = trim($path, '/');

$parts = array_filter(explode('/', $path));
$resource = isset($parts[0]) ? $parts[0] : null;
$id = isset($parts[1]) ? (int)$parts[1] : null;

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = !empty($input) ? json_decode($input, true) : [];

$allowedTables = ['produtos', 'clientes', 'vendas', 'venda_itens', 'financeiro', 'franquias'];

if (!$resource || !in_array($resource, $allowedTables)) {
    http_response_code(400);
    echo json_encode(['error' => 'Recurso inválido']);
    exit;
}

try {
    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                http_response_code(404);
                echo json_encode(['error' => 'Não encontrado']);
                exit;
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM $resource ORDER BY id DESC");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($result);

    } elseif ($method === 'POST') {
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados vazios']);
            exit;
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $resource ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        echo json_encode([
            'id' => (int)$pdo->lastInsertId(),
            'success' => true
        ]);

    } elseif ($method === 'PUT') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID necessário']);
            exit;
        }
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados vazios']);
            exit;
        }
        
        $updates = [];
        $values = [];
        foreach ($data as $key => $value) {
            $updates[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;
        
        $sql = "UPDATE $resource SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID necessário']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM $resource WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>