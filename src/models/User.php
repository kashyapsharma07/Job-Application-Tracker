<?php
// src/models/User.php

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, job_title) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]),
            $data['job_title'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowed = ['name', 'job_title', 'avatar', 'email_alerts', 'interview_reminders', 'marketing_comms', 'plan'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($values);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]), $id]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $excludeId]);
        return (bool)$stmt->fetch();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ── Admin Methods ──────────────────────────────────────────────────────────
    
    public function getAllUsers(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, plan, role, created_at FROM users ORDER BY created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalUsers(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users');
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    public function deleteUser(int $userId): bool
    {
        // Delete related data first
        $this->db->prepare('DELETE FROM applications WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('DELETE FROM resumes WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('DELETE FROM reminders WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare('DELETE FROM application_events WHERE user_id = ?')->execute([$userId]);
        
        // Delete user
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$userId]);
    }

    public function updateRole(int $userId, string $role): bool
    {
        $validRoles = ['user', 'admin'];
        if (!in_array($role, $validRoles)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $userId]);
    }
}

