<?php
// src/models/Resume.php

class Resume {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM resumes WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $userId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM resumes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int {
        if (!empty($data['is_default'])) {
            $this->clearDefault($userId);
        }
        $stmt = $this->db->prepare(
            'INSERT INTO resumes (user_id, filename, original_name, file_size, version_label, is_default)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['filename'],
            $data['original_name'],
            $data['file_size'] ?? null,
            $data['version_label'] ?? null,
            $data['is_default'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id, int $userId): ?string {
        $resume = $this->getById($id, $userId);
        if (!$resume) return null;
        $stmt = $this->db->prepare('DELETE FROM resumes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $resume['filename'];
    }

    public function setDefault(int $id, int $userId): bool {
        $this->clearDefault($userId);
        $stmt = $this->db->prepare('UPDATE resumes SET is_default = 1 WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    private function clearDefault(int $userId): void {
        $stmt = $this->db->prepare('UPDATE resumes SET is_default = 0 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
