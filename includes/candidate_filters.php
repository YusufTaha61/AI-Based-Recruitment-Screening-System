<?php
// Filtering and sorting for the candidate list.
// Used by the history page and the CSV export, so both always show the same candidates.

// Sort options: key => [label, SQL]
const CANDIDATE_SORTS = [
    'newest' => ['En yeni önce', 'c.created_at DESC, c.id DESC'],
    'score' => ['Puana göre (yüksekten)', 'c.score DESC'],
    'name' => ['Ada göre (A-Z)', 'c.name COLLATE NOCASE'],
];

// Decision filter, same score limits as decisionLabel()
const DECISION_RULES = [
    'high' => 'c.score >= 70',
    'medium' => 'c.score >= 40 AND c.score < 70',
    'low' => 'c.score < 40',
];

/**
 * Reads the filters from the address (?q=...&decision=...) and returns
 * the candidates of one team as
 * [list of candidates, the filters that were used].
 */
function filteredCandidates(PDO $db, array $get, int $teamId): array
{
    $filters = [
        'q' => trim($get['q'] ?? ''),
        'decision' => $get['decision'] ?? '',
        'position' => $get['position'] ?? '',
        'level' => $get['level'] ?? '',
        'status' => $get['status'] ?? '',
        'sort' => isset(CANDIDATE_SORTS[$get['sort'] ?? '']) ? $get['sort'] : 'newest',
    ];

    // Build the WHERE part step by step; values go in $params, never into the SQL text
    // Only the candidates of the user's own team
    $where = ['c.team_id = ?'];
    $params = [$teamId];
    if ($filters['q'] !== '') {
        $where[] = 'c.name LIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (isset(DECISION_RULES[$filters['decision']])) {
        $where[] = DECISION_RULES[$filters['decision']];
    }
    if (isset(CANDIDATE_POSITIONS[$filters['position']])) {
        $where[] = 'c.position = ?';
        $params[] = $filters['position'];
    }
    if (isset(CANDIDATE_LEVELS[$filters['level']])) {
        $where[] = 'c.level = ?';
        $params[] = $filters['level'];
    }
    if (in_array($filters['status'], CANDIDATE_STATUSES, true)) {
        $where[] = 'c.status = ?';
        $params[] = $filters['status'];
    }

    $sql = "SELECT c.*, COALESCE(u.name, 'Silinmiş kullanıcı') AS evaluator
            FROM candidates c
            LEFT JOIN users u ON u.id = c.user_id"
        . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY ' . CANDIDATE_SORTS[$filters['sort']][1];
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return [$stmt->fetchAll(PDO::FETCH_ASSOC), $filters];
}
