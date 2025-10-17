// ============================================
// ARQUIVO 1: config/database.php
// ============================================

<?php

class Database {
    private $pdo;
    private $dbPath;

    public function __construct() {
        // Caminho do banco de dados
        $this->dbPath = __DIR__ . '/../storage/autofranquia.db';
        
        // Criar pasta storage se não existir
        if (!is_dir(dirname($this->dbPath))) {
            mkdir(dirname($this->dbPath), 0755, true);
        }
        
        $this->connect();
        $this->initTables();
    }

    private function connect() {
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Erro ao conectar ao banco: ' . $e->getMessage()]));
        }
    }

    private function initTables() {
        $sql = [
            "CREATE TABLE IF NOT EXISTS produtos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                sku TEXT UNIQUE NOT NULL,
                categoria TEXT NOT NULL,
                preco REAL NOT NULL,
                estoque INTEGER NOT NULL,
                imagem TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                tipoDocumento TEXT NOT NULL,
                documento TEXT,
                email TEXT,
                telefone TEXT,
                veiculo TEXT,
                logradouro TEXT,
                bairro TEXT,
                cep TEXT,
                cidade TEXT,
                uf TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS vendas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cliente TEXT NOT NULL,
                valor REAL NOT NULL,
                data DATE NOT NULL,
                franquia_id INTEGER NOT NULL,
                pagamento TEXT,
                desconto REAL DEFAULT 0,
                acrescimo REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS venda_itens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                venda_id INTEGER NOT NULL,
                produto_id INTEGER NOT NULL,
                quantidade INTEGER NOT NULL,
                preco_unitario REAL NOT NULL,
                FOREIGN KEY (venda_id) REFERENCES vendas(id),
                FOREIGN KEY (produto_id) REFERENCES produtos(id)
            )",

            "CREATE TABLE IF NOT EXISTS financeiro (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tipo TEXT NOT NULL,
                valor REAL NOT NULL,
                descricao TEXT NOT NULL,
                data DATE NOT NULL,
                franquia_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS franquias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                cidade TEXT NOT NULL,
                cnpj TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($sql as $query) {
            try {
                $this->pdo->exec($query);
            } catch (PDOException $e) {
                // Tabela já existe, ignora
            }
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $id) {
        $updates = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $updates[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?";
        return $this->query($sql, $values);
    }

    public function delete($table, $id) {
        return $this->query("DELETE FROM $table WHERE id = ?", [$id]);
    }

    public function getLastId() {
        return $this->pdo->lastInsertId();
    }
}

?>



