<?php
class ExpenseStorage
{
    private PDO $db;

    private array $cats = [
        'Food & Dining', 'Transport', 'Housing', 'Healthcare',
        'Entertainment', 'Shopping', 'Education', 'Utilities', 'Travel', 'Other'
    ];

    public function __construct()
    {
        // Render provides one single DATABASE_URL variable automatically
        // when you link a PostgreSQL database to your web service.
        // It looks like: postgres://user:password@host:5432/dbname
        $url = getenv('DATABASE_URL');

        if (!$url) {
            throw new RuntimeException(
                'DATABASE_URL is not set. ' .
                'Go to Render dashboard → your Web Service → Environment → ' .
                'link your PostgreSQL database, or add DATABASE_URL manually.'
            );
        }

        $parts = parse_url($url);

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/') 
        );

        $this->db = new PDO($dsn, $parts['user'], $parts['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    // READ 

    public function all(array $filters = []): array
    {
        $sql    = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        if (!empty($filters['category'])) {
            $sql     .= ' AND category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['month'])) {
            // TO_CHAR() is the PostgreSQL equivalent of MySQL's DATE_FORMAT()
            $sql     .= " AND TO_CHAR(date, 'YYYY-MM') = ?";
            $params[] = $filters['month'];
        }
        if (!empty($filters['search'])) {
            // ILIKE = case-insensitive LIKE in PostgreSQL
            $sql     .= ' AND description ILIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY date DESC, created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function categories(): array
    {
        return $this->cats;
    }

    public function summary(): array
    {
        $total = $this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses'
        )->fetchColumn();

        // TO_CHAR(NOW(), 'YYYY-MM') = current month in PostgreSQL
        $monthTotal = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses
             WHERE TO_CHAR(date, 'YYYY-MM') = TO_CHAR(NOW(), 'YYYY-MM')"
        )->fetchColumn();

        $byCatRows = $this->db->query(
            'SELECT category, SUM(amount) AS amt FROM expenses
             GROUP BY category ORDER BY amt DESC'
        )->fetchAll();

        $byCat = [];
        foreach ($byCatRows as $row) {
            $byCat[$row['category']] = (float) $row['amt'];
        }

        $byMonthRows = $this->db->query(
            "SELECT TO_CHAR(date, 'YYYY-MM') AS month, SUM(amount) AS amt
             FROM expenses
             GROUP BY month
             ORDER BY month DESC
             LIMIT 6"
        )->fetchAll();

        $byMonth = [];
        foreach ($byMonthRows as $row) {
            $byMonth[$row['month']] = (float) $row['amt'];
        }

        return compact('total', 'byCat', 'byMonth', 'monthTotal');
    }

    // WRITE 
    public function create(array $input): array
    {
        $e       = $this->validate($input);
        $e['id'] = 'exp_' . bin2hex(random_bytes(6));

        $this->db->prepare(
            'INSERT INTO expenses (id, amount, date, description, category, notes)
             VALUES (:id, :amount, :date, :description, :category, :notes)'
        )->execute([
            ':id'          => $e['id'],
            ':amount'      => $e['amount'],
            ':date'        => $e['date'],
            ':description' => $e['description'],
            ':category'    => $e['category'],
            ':notes'       => $e['notes'],
        ]);

        return $e;
    }

    public function update(string $id, array $input): array
    {
        $e = $this->validate($input);

        $stmt = $this->db->prepare(
            'UPDATE expenses
             SET amount = :amount, date = :date, description = :description,
                 category = :category, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            ':amount'      => $e['amount'],
            ':date'        => $e['date'],
            ':description' => $e['description'],
            ':category'    => $e['category'],
            ':notes'       => $e['notes'],
            ':id'          => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Expense not found: $id");
        }

        return array_merge($e, ['id' => $id]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Expense not found: $id");
        }

        return true;
    }

    // VALIDATION 

    private function validate(array $in): array
    {
        $amount = filter_var($in['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date'] ?? '')) {
            throw new InvalidArgumentException('Date must be YYYY-MM-DD.');
        }
        $description = trim($in['description'] ?? '');
        if ($description === '') {
            throw new InvalidArgumentException('Description is required.');
        }
        $category = trim($in['category'] ?? '');
        if (!in_array($category, $this->cats, true)) {
            throw new InvalidArgumentException('Invalid category.');
        }

        return [
            'amount'      => round($amount, 2),
            'date'        => $in['date'],
            'description' => $description,
            'category'    => $category,
            'notes'       => trim($in['notes'] ?? ''),
        ];
    }
}