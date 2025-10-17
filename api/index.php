<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

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
    "CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT UNIQUE NOT NULL, senha TEXT NOT NULL, role TEXT NOT NULL, franquia_id INTEGER, ativo INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS franquias (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, cidade TEXT NOT NULL, cnpj TEXT UNIQUE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS produtos (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, sku TEXT UNIQUE NOT NULL, categoria TEXT NOT NULL, preco REAL NOT NULL, estoque INTEGER NOT NULL, imagem TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, tipoDocumento TEXT NOT NULL, documento TEXT, email TEXT, telefone TEXT, veiculo TEXT, logradouro TEXT, bairro TEXT, cep TEXT, cidade TEXT, uf TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS vendas (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente TEXT NOT NULL, valor REAL NOT NULL, data DATE NOT NULL, franquia_id INTEGER NOT NULL, pagamento TEXT, desconto REAL DEFAULT 0, acrescimo REAL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS venda_itens (id INTEGER PRIMARY KEY AUTOINCREMENT, venda_id INTEGER NOT NULL, produto_id INTEGER NOT NULL, quantidade INTEGER NOT NULL, preco_unitario REAL NOT NULL, FOREIGN KEY (venda_id) REFERENCES vendas(id), FOREIGN KEY (produto_id) REFERENCES produtos(id))",
    "CREATE TABLE IF NOT EXISTS financeiro (id INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT NOT NULL, valor REAL NOT NULL, descricao TEXT NOT NULL, data DATE NOT NULL, franquia_id INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
);

foreach ($sql_init as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
    }
}

$verifyUser = function() {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
};

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/index.php', '', $path);
$path = trim($path, '/');

$parts = array_filter(explode('/', $path));
$action = isset($parts[0]) ? $parts[0] : null;
$resource = isset($parts[1]) ? $parts[1] : null;
$id = isset($parts[2]) ? (int)$parts[2] : null;

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = !empty($input) ? json_decode($input, true) : [];

try {
    /* 
ATUALIZE o arquivo api/index.php
Procure a seção "if ($action === 'auth')" e SUBSTITUA por:
*/

if ($action === 'auth') {
    if ($resource === 'login' && $method === 'POST') {
        $email = $data['email'] ?? '';
        $senha = $data['senha'] ?? '';
        
        if (!$email || !$senha) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha obrigatórios']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, nome, email, role, franquia_id, senha FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Email ou senha inválidos']);
            exit;
        }
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_role'] = $usuario['role'];
        $_SESSION['usuario_franquia_id'] = $usuario['franquia_id'];
        
        unset($usuario['senha']);
        echo json_encode([
            'success' => true,
            'usuario' => $usuario
        ]);
    }
    elseif ($resource === 'logout' && $method === 'POST') {
        session_destroy();
        echo json_encode(['success' => true]);
    }
    elseif ($resource === 'perfil' && $method === 'GET') {
        $verifyUser();
        
        $stmt = $pdo->prepare("SELECT id, nome, email, role, franquia_id FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($usuario);
    }
    elseif ($resource === 'usuarios' && $method === 'GET') {
        $verifyUser();
        
        if ($_SESSION['usuario_role'] !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, nome, email, role, franquia_id, ativo FROM usuarios ORDER BY id DESC");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($usuarios);
    }
    elseif ($resource === 'usuarios' && $id && $method === 'PUT') {
        $verifyUser();
        
        if ($_SESSION['usuario_role'] !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados vazios']);
            exit;
        }
        
        $updates = [];
        $values = [];
        
        if (isset($data['nome'])) {
            $updates[] = "nome = ?";
            $values[] = $data['nome'];
        }
        if (isset($data['email'])) {
            $updates[] = "email = ?";
            $values[] = $data['email'];
        }
        if (isset($data['role'])) {
            $updates[] = "role = ?";
            $values[] = $data['role'];
        }
        if (isset($data['franquia_id'])) {
            $updates[] = "franquia_id = ?";
            $values[] = $data['franquia_id'];
        }
        if (isset($data['senha']) && !empty($data['senha'])) {
            $updates[] = "senha = ?";
            $values[] = password_hash($data['senha'], PASSWORD_BCRYPT);
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nada para atualizar']);
            exit;
        }
        
        $values[] = $id;
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        echo json_encode(['success' => true]);
    }
    elseif ($resource === 'usuarios' && $id && $method === 'DELETE') {
        $verifyUser();
        
        if ($_SESSION['usuario_role'] !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        if ($id == $_SESSION['usuario_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Não é possível deletar sua própria conta']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($resource === 'usuarios' && $method === 'POST') {
        $verifyUser();
        
        if ($_SESSION['usuario_role'] !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        $nome = $data['nome'] ?? '';
        $email = $data['email'] ?? '';
        $senha = $data['senha'] ?? '';
        $role = $data['role'] ?? 'colaborador';
        $franquia_id = $data['franquia_id'] ?? null;
        
        if (!$nome || !$email || !$senha) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome, email e senha obrigatórios']);
            exit;
        }
        
        try {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, role, franquia_id, ativo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nome, $email, $hash, $role, $franquia_id]);
            
            echo json_encode([
                'id' => (int)$pdo->lastInsertId(),
                'success' => true
            ]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Email já existe']);
        }
    }
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Ação não encontrada']);
    }
}
    else {
        $verifyUser();
        
        $allowedTables = ['produtos', 'clientes', 'vendas', 'venda_itens', 'financeiro', 'franquias'];
        
        if (!$action || !in_array($action, $allowedTables)) {
            http_response_code(400);
            echo json_encode(['error' => 'Recurso inválido']);
            exit;
        }
        
        if ($method === 'GET') {
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM $action WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Não encontrado']);
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM $action ORDER BY id DESC");
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($result);
        }
        elseif ($method === 'POST') {
            if (empty($data)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados vazios']);
                exit;
            }
            
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO $action ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            echo json_encode([
                'id' => (int)$pdo->lastInsertId(),
                'success' => true
            ]);
        }
        elseif ($method === 'PUT') {
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
            
            $sql = "UPDATE $action SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            echo json_encode(['success' => true]);
        }
        elseif ($method === 'DELETE') {
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID necessário']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM $action WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
        }
        else {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
        }
    }
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>