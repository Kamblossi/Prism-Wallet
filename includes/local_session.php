<?php

class LocalSession {
    private PDO $pdo;
    private ?array $user = null;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->load();
    }

    private function load(): void {
        $uid = $_SESSION['user_id'] ?? null;
        if ($uid) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $uid]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    public function isLoggedIn(): bool {
        return $this->user !== null;
    }

    public function getUser(): ?array {
        return $this->user;
    }

    public function getUserId(): ?int {
        return $this->user['id'] ?? null;
    }

    public function isAdmin(): bool {
        return (bool)($this->user['is_admin'] ?? false);
    }

    public function requireAuth(): array {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
        return $this->user;
    }
}

?>
