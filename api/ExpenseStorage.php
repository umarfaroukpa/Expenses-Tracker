<?php
/**
 * ExpenseStorage — MySQL backend
 *
 * BEGINNER CONCEPTS USED HERE:
 *   PDO       — PHP's standard way to connect to any database
 *   $pdo->prepare()  — build a SQL query with placeholders (safe from SQL injection)
 *   $stmt->execute() — run it, passing in the real values
 *   fetch()          — get one row back as an array
 *   fetchAll()       — get all rows back as an array of arrays
 */
class ExpenseStorage
{
    // holds the database connection
    private PDO $db;   

    private array $cats = [
        'Food & Dining', 'Transport', 'Housing', 'Healthcare',
        'Entertainment', 'Shopping', 'Education', 'Utilities', 'Travel', 'Other'
    ];

    public function __construct()
    {
        // Database credentials 
        // Change these to match your MySQL setup.
        $host = 'localhost';  // almost always localhost
        $db   = 'ledgeer';     // the database name you created
        $user = 'root';       // your MySQL username
        $pass = '';           // your MySQL password (empty string if none)

        // DSN = "Data Source Name" — tells PDO which driver and database to use
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        // PDO options:
        //   ERRMODE_EXCEPTION  → throw an error instead of silently failing (always use this)
        //   FETCH_ASSOC        → return rows as ['column' => 'value'] arrays, not numbered arrays
        $this->db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    // READ 

    public function all(array $filters = []): array
    {
        // Start with a base query, then add conditions depending on what filters were passed
        $sql    = 'SELECT * FROM expenses WHERE 1=1';
        $params = [];

        // 1=1 is a harmless "always true" condition — it lets us safely append
        // AND clauses without worrying about whether this is the first one or not.

        if (!empty($filters['category'])) {
            $sql     .= ' AND category = ?';  // ? is a placeholder
            $params[] = $filters['category']; // real value goes here, matched by position
        }
        if (!empty($filters['month'])) {
            // DATE_FORMAT is a MySQL function that formats a date column
            // '%Y-%m' produces "2026-05" from a date like 2026-05-10
            $sql     .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
            $params[] = $filters['month'];
        }
        if (!empty($filters['search'])) {
            // LIKE with % wildcards = "contains" search
            $sql     .= ' AND description LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY date DESC, created_at DESC';

        // prepare() compiles the SQL; execute() runs it with the real values
        // Using ? placeholders (not string interpolation) prevents SQL injection
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();  // returns all matching rows as an array
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();        // fetch() returns one row, or false if not found
        return $row ?: null;          // turn false into null to be consistent
    }

    public function categories(): array
    {
        return $this->cats;  // categories are static, no need to store them in MySQL
    }

    public function summary(): array
    {
        // COALESCE(SUM(...), 0) — if there are no rows, SUM() returns NULL.
        // COALESCE picks the first non-NULL value, so we get 0 instead of NULL.
        $total = $this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses'
        )->fetchColumn();  // fetchColumn() gets just the first column of the first row

        // DATE_FORMAT(NOW(), '%Y-%m') produces the current month, e.g. "2026-05"
        $monthTotal = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses
             WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
        )->fetchColumn();

        // GROUP BY category — MySQL groups rows with the same category together
        // and SUM() gives us the total for each group
        $byCatRows = $this->db->query(
            'SELECT category, SUM(amount) AS amt FROM expenses
             GROUP BY category ORDER BY amt DESC'
        )->fetchAll();

        // Reshape from [['category'=>'Food','amt'=>45.50], ...] to ['Food' => 45.50, ...]
        $byCat = [];
        foreach ($byCatRows as $row) {
            $byCat[$row['category']] = (float) $row['amt'];
        }

        // Last 6 months, most recent first
        $byMonthRows = $this->db->query(
            "SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(amount) AS amt
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
        // compact() builds ['total'=>..., 'byCat'=>..., ...] from local variables
    }

    // WRITE

    public function create(array $input): array
    {
        $e       = $this->validate($input);
        $e['id'] = 'exp_' . bin2hex(random_bytes(6));  // random unique ID

        // Named placeholders (:id, :amount) are cleaner than ? when there are many columns
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
            // rowCount() = how many rows were changed
            // 0 means no row had that id — it doesn't exist
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

    // VALIDATION (This is same as JSON version) 

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