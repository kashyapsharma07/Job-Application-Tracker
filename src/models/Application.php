<?php
// src/models/Application.php

class Application {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(int $userId, array $filters = []): array {
        $sql = 'SELECT a.*, r.version_label as resume_label, r.original_name as resume_name
                FROM applications a
                LEFT JOIN resumes r ON a.resume_id = r.id
                WHERE a.user_id = ?';
        $params = [$userId];

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (a.company LIKE ? OR a.job_title LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= ' ORDER BY a.updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.*, r.version_label as resume_label, r.original_name as resume_name
             FROM applications a
             LEFT JOIN resumes r ON a.resume_id = r.id
             WHERE a.id = ? AND a.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO applications (user_id, company, job_title, job_url, job_type, status, salary_range, resume_id, notes, applied_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['company'],
            $data['job_title'],
            $data['job_url'] ?? null,
            $data['job_type'] ?? 'not_specified',
            $data['status'] ?? 'wishlist',
            $data['salary_range'] ?? null,
            $data['resume_id'] ?? null,
            $data['notes'] ?? null,
            $data['applied_at'] ?? null,
        ]);
        $appId = (int)$this->db->lastInsertId();
        $this->addEvent($appId, 'status_change', 'Application created', 'Status: ' . ($data['status'] ?? 'wishlist'));
        return $appId;
    }

    public function update(int $id, int $userId, array $data): bool {
        $old = $this->getById($id, $userId);
        $stmt = $this->db->prepare(
            'UPDATE applications SET company=?, job_title=?, job_url=?, job_type=?, status=?,
             salary_range=?, resume_id=?, notes=?, applied_at=?
             WHERE id=? AND user_id=?'
        );
        $ok = $stmt->execute([
            $data['company'],
            $data['job_title'],
            $data['job_url'] ?? null,
            $data['job_type'] ?? 'not_specified',
            $data['status'],
            $data['salary_range'] ?? null,
            $data['resume_id'] ?? null,
            $data['notes'] ?? null,
            $data['applied_at'] ?? null,
            $id,
            $userId,
        ]);
        if ($ok && $old && $old['status'] !== $data['status']) {
            $this->addEvent($id, 'status_change', 'Status updated',
                'Changed from ' . $old['status'] . ' to ' . $data['status']);
        }
        return $ok;
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public function updateStatus(int $id, int $userId, string $status): bool {
        $old = $this->getById($id, $userId);
        $stmt = $this->db->prepare('UPDATE applications SET status = ? WHERE id = ? AND user_id = ?');
        $ok = $stmt->execute([$status, $id, $userId]);
        if ($ok && $old && $old['status'] !== $status) {
            $this->addEvent($id, 'status_change', 'Status updated',
                'Changed from ' . $old['status'] . ' to ' . $status);
        }
        return $ok;
    }

    public function countByStatus(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) as count FROM applications WHERE user_id = ? GROUP BY status'
        );
        $stmt->execute([$userId]);
        $result = ['wishlist'=>0,'applied'=>0,'interviewing'=>0,'offer'=>0,'rejected'=>0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        return $result;
    }

    public function countByMonth(int $userId, int $months = 6): array {
        $stmt = $this->db->prepare(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count
             FROM applications
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month ORDER BY month ASC'
        );
        $stmt->execute([$userId, $months]);
        return $stmt->fetchAll();
    }

    public function topCompanies(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            'SELECT company, COUNT(*) as count, status
             FROM applications WHERE user_id = ?
             GROUP BY company ORDER BY count DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ── NEW METHOD 1 ─────────────────────────────────────────────────────────
    // Returns funnel conversion counts: applied → interviewing → offer
    // Each step also carries a conversion % relative to the previous step.
    public function conversionFunnel(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) as count
             FROM applications
             WHERE user_id = ? AND status IN ("applied","interviewing","offer","rejected")
             GROUP BY status'
        );
        $stmt->execute([$userId]);
        $raw = ['applied'=>0,'interviewing'=>0,'offer'=>0,'rejected'=>0];
        foreach ($stmt->fetchAll() as $row) {
            $raw[$row['status']] = (int)$row['count'];
        }

        // Total "in pipeline" = applied + interviewing + offer + rejected
        $total = array_sum($raw);

        $funnel = [];

        // Step 1 — Applied (100% baseline)
        $funnel[] = [
            'label'   => 'Applied',
            'status'  => 'applied',
            'count'   => $total,
            'pct'     => 100,
            'color'   => '#1a73e8',
        ];

        // Step 2 — Got an interview (interviewing + offer, i.e. made it past applied)
        $interviewed = $raw['interviewing'] + $raw['offer'];
        $funnel[] = [
            'label'   => 'Interviewed',
            'status'  => 'interviewing',
            'count'   => $interviewed,
            'pct'     => $total > 0 ? round(($interviewed / $total) * 100) : 0,
            'color'   => '#f59e0b',
        ];

        // Step 3 — Received offer
        $funnel[] = [
            'label'   => 'Offer received',
            'status'  => 'offer',
            'count'   => $raw['offer'],
            'pct'     => $total > 0 ? round(($raw['offer'] / $total) * 100) : 0,
            'color'   => '#10b981',
        ];

        return $funnel;
    }

    // ── NEW METHOD 2 ─────────────────────────────────────────────────────────
    // Returns applications stuck in "applied" or "interviewing" for > $days days
    // with no status update. These are the "needs follow-up" candidates.
    public function stalledApps(int $userId, int $days = 7): array {
        $stmt = $this->db->prepare(
            'SELECT id, company, job_title, status, updated_at,
                    DATEDIFF(NOW(), updated_at) as days_stalled
             FROM applications
             WHERE user_id = ?
               AND status IN ("applied", "interviewing")
               AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY updated_at ASC'
        );
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll();
    }

    // ── NEW METHOD 3 ─────────────────────────────────────────────────────────
    // Finds the day of the week on which the user's applications most often
    // progressed (i.e. moved to interviewing or offer status).
    // Returns the day name (e.g. "Tuesday") or null if not enough data.
    public function bestDayOfWeek(int $userId): ?string {
        $stmt = $this->db->prepare(
            'SELECT DAYNAME(created_at) as day_name, COUNT(*) as count
             FROM applications
             WHERE user_id = ?
               AND status IN ("interviewing", "offer")
             GROUP BY day_name
             ORDER BY count DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['day_name'] : null;
    }

    // ── Application events / timeline ────────────────────────────────────────
    public function addEvent(int $appId, string $type, string $title, ?string $desc = null, ?string $date = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO application_events (application_id, event_type, title, description, event_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$appId, $type, $title, $desc, $date ?? date('Y-m-d H:i:s')]);
        return (int)$this->db->lastInsertId();
    }

    public function getEvents(int $appId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM application_events WHERE application_id = ? ORDER BY event_date DESC'
        );
        $stmt->execute([$appId]);
        return $stmt->fetchAll();
    }

    public function getCalendarEvents(int $userId, string $year, string $month): array {
        $stmt = $this->db->prepare(
            'SELECT ae.*, a.company, a.job_title
             FROM application_events ae
             JOIN applications a ON ae.application_id = a.id
             WHERE a.user_id = ?
               AND DATE_FORMAT(ae.event_date, "%Y-%m") = ?
             ORDER BY ae.event_date ASC'
        );
        $stmt->execute([$userId, "$year-$month"]);
        return $stmt->fetchAll();
    }

    public function getUpcomingEvents(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            'SELECT ae.*, a.company, a.job_title
             FROM application_events ae
             JOIN applications a ON ae.application_id = a.id
             WHERE a.user_id = ? AND ae.event_date >= NOW()
             ORDER BY ae.event_date ASC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}