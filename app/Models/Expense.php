<?php

/*
=====================================================
EXPENSE MODEL
Manage Fixed Expenses (Office & Utilities)
=====================================================
*/

class Expense {

    public static function create(string $title, float $amount, string $category, string $date): bool {
        $stmt = db()->prepare("
            INSERT INTO eco_expenses (title, amount, category, expense_date)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$title, $amount, $category, $date]);
    }

    public static function delete(int $id): bool {
        $stmt = db()->prepare("DELETE FROM eco_expenses WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getFiltered(string $period, string $start = '', string $end = ''): array {
        $cond = self::getDateRangeSQL($period, $start, $end, 'expense_date');
        return db()->query("
            SELECT * FROM eco_expenses
            WHERE {$cond}
            ORDER BY expense_date DESC, id DESC
        ")->fetchAll();
    }

    public static function getPeriodTotals(string $period, string $start = '', string $end = ''): array {
        $cond = self::getDateRangeSQL($period, $start, $end, 'expense_date');
        $rows = db()->query("
            SELECT category, COALESCE(SUM(amount), 0) AS total
            FROM eco_expenses
            WHERE {$cond}
            GROUP BY category
        ")->fetchAll();

        $totals = ['office' => 0.00, 'utility' => 0.00, 'other' => 0.00, 'total' => 0.00];
        foreach ($rows as $r) {
            $cat = $r['category'];
            $val = floatval($r['total']);
            if (array_key_exists($cat, $totals)) {
                $totals[$cat] = $val;
            }
            $totals['total'] += $val;
        }
        return $totals;
    }

    public static function getDateRangeSQL(string $period, string $start_date = '', string $end_date = '', string $column = 'created_at'): string {
        $now = date('Y-m-d');
        switch ($period) {
            case 'today':
                return " DATE({$column}) = '{$now}' ";
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return " DATE({$column}) = '{$yesterday}' ";
            case 'week':
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                return " DATE({$column}) BETWEEN '{$startOfWeek}' AND '{$endOfWeek}' ";
            case 'last_week':
                $startOfLastWeek = date('Y-m-d', strtotime('monday last week'));
                $endOfLastWeek = date('Y-m-d', strtotime('sunday last week'));
                return " DATE({$column}) BETWEEN '{$startOfLastWeek}' AND '{$endOfLastWeek}' ";
            case 'month':
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                return " DATE({$column}) BETWEEN '{$startOfMonth}' AND '{$endOfMonth}' ";
            case 'year':
                $startOfYear = date('Y-01-01');
                $endOfYear = date('Y-12-31');
                return " DATE({$column}) BETWEEN '{$startOfYear}' AND '{$endOfYear}' ";
            case 'custom':
                if ($start_date && $end_date) {
                    return " DATE({$column}) BETWEEN '{$start_date}' AND '{$end_date}' ";
                } elseif ($start_date) {
                    return " DATE({$column}) >= '{$start_date}' ";
                } elseif ($end_date) {
                    return " DATE({$column}) <= '{$end_date}' ";
                }
                return " 1=1 ";
            case 'all':
            default:
                return " 1=1 ";
        }
    }
}
