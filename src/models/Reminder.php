<?php
// src/models/Reminder.php

class Reminder {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT r.*, a.company, a.job_title
             FROM reminders r
             LEFT JOIN applications a ON r.application_id = a.id
             WHERE r.user_id = ?
             ORDER BY r.remind_at ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getPending(): array {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.email, u.name as user_name, a.company, a.job_title
             FROM reminders r
             JOIN users u ON r.user_id = u.id
             LEFT JOIN applications a ON r.application_id = a.id
             WHERE r.sent = 0 AND r.remind_at <= NOW()
               AND u.interview_reminders = 1'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $userId, array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO reminders (user_id, application_id, title, description, remind_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['application_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['remind_at'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function markSent(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE reminders SET sent = 1, sent_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare('DELETE FROM reminders WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }
}
