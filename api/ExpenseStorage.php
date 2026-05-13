<?php
/**
 * ExpenseStorage - JSON-backed storage with a clean interface
 * designed to be swapped for MySQL later with minimal changes.
 */
class ExpenseStorage
{
    private string $file;
    private array  $data;

    public function __construct(string $file = __DIR__ . '/../data/expenses.json')
    {
        $this->file = $file;
        $this->load();
    }

    // read 

    public function all(array $filters = []): array
    {
        $rows = $this->data['expenses'];

        if (!empty($filters['category'])) {
            $rows = array_filter($rows, fn($e) => $e['category'] === $filters['category']);
        }
        if (!empty($filters['month'])) {          // "YYYY-MM"
            $rows = array_filter($rows, fn($e) => str_starts_with($e['date'], $filters['month']));
        }
        if (!empty($filters['search'])) {
            $q = strtolower($filters['search']);
            $rows = array_filter($rows, fn($e) => str_contains(strtolower($e['description']), $q));
        }

        // newest first
        usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
        return array_values($rows);
    }

    public function find(string $id): ?array
    {
        foreach ($this->data['expenses'] as $e) {
            if ($e['id'] === $id) return $e;
        }
        return null;
    }

    public function categories(): array
    {
        return $this->data['categories'];
    }

    public function summary(): array
    {
        $expenses = $this->data['expenses'];
        $total    = array_sum(array_column($expenses, 'amount'));

        // by category
        $byCat = [];
        foreach ($expenses as $e) {
            $byCat[$e['category']] = ($byCat[$e['category']] ?? 0) + $e['amount'];
        }
        arsort($byCat);

        // by month (last 6)
        $byMonth = [];
        foreach ($expenses as $e) {
            $m = substr($e['date'], 0, 7);
            $byMonth[$m] = ($byMonth[$m] ?? 0) + $e['amount'];
        }
        krsort($byMonth);
        $byMonth = array_slice($byMonth, 0, 6, true);

        // current month
        $thisMonth = date('Y-m');
        $monthTotal = array_sum(array_filter(
            array_map(fn($e) => str_starts_with($e['date'], $thisMonth) ? $e['amount'] : 0, $expenses)
        ));

        return compact('total', 'byCat', 'byMonth', 'monthTotal');
    }

    // write 

    public function create(array $input): array
    {
        $expense = $this->validate($input);
        $expense['id']         = $this->newId();
        $expense['created_at'] = date('c');

        $this->data['expenses'][] = $expense;
        $this->save();
        return $expense;
    }

    public function update(string $id, array $input): array
    {
        $expense = $this->validate($input);
        foreach ($this->data['expenses'] as &$e) {
            if ($e['id'] === $id) {
                $e = array_merge($e, $expense, ['id' => $id]);
                $this->save();
                return $e;
            }
        }
        throw new RuntimeException("Expense not found: $id");
    }

    public function delete(string $id): bool
    {
        $before = count($this->data['expenses']);
        $this->data['expenses'] = array_values(
            array_filter($this->data['expenses'], fn($e) => $e['id'] !== $id)
        );
        if (count($this->data['expenses']) === $before) {
            throw new RuntimeException("Expense not found: $id");
        }
        $this->save();
        return true;
    }

    // private

    private function validate(array $in): array
    {
        $amount = filter_var($in['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number.');
        }
        $date = $in['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Date must be YYYY-MM-DD.');
        }
        $description = trim($in['description'] ?? '');
        if ($description === '') {
            throw new InvalidArgumentException('Description is required.');
        }
        $category = trim($in['category'] ?? '');
        if (!in_array($category, $this->data['categories'], true)) {
            throw new InvalidArgumentException('Invalid category.');
        }

        return [
            'amount'      => round($amount, 2),
            'date'        => $date,
            'description' => $description,
            'category'    => $category,
            'notes'       => trim($in['notes'] ?? ''),
        ];
    }

    private function load(): void
    {
        if (!file_exists($this->file)) {
            throw new RuntimeException("Data file not found: {$this->file}");
        }
        $this->data = json_decode(file_get_contents($this->file), true);
    }

    private function save(): void
    {
        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    private function newId(): string
    {
        return 'exp_' . bin2hex(random_bytes(6));
    }
}
