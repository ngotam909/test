<?php
namespace Inc;

use PDO;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

class BookService
{
    private const DEFAULT_LOAN_DAYS = 14;

    public function __construct(private DB $db) {}
    public function create(array $data): int
    {
        $pdo = $this->db->connect();

        $stmt = $pdo->prepare(
            'INSERT INTO books (title, author, isbn, price, description, published_at, total_copies, available_copies)
             VALUES (:title, :author, :isbn, :price, :description, :published_at, :total_copies, :available_copies)'
        );

        $title   = trim((string)($data['title'] ?? ''));
        $author  = trim((string)($data['author'] ?? ''));
        if ($title === '' || $author === '') {
            throw new RuntimeException('Title/Author is required');
        }

        $total = max(1, (int)($data['total_copies'] ?? 1));
        $avail = array_key_exists('available_copies', $data)
               ? max(0, min((int)$data['available_copies'], $total))
               : $total;

        $stmt->execute([
            ':title' => $title,
            ':author' => $author,
            ':isbn' => $data['isbn'] ?? null,
            ':price' => isset($data['price']) ? (float)$data['price'] : 0,
            ':description' => $data['description'] ?? null,
            ':published_at' => $data['published_at'] ?? null, // 'YYYY-MM-DD' | null
            ':total_copies' => $total,
            ':available_copies'=> $avail,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $pdo = $this->db->connect();
        $sql = 'SELECT * FROM books WHERE id = :id';
        if (!$withDeleted) $sql .= ' AND deleted_at IS NULL';
        $sql .= ' LIMIT 1';

        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->db->connect();

        $where  = [];
        $params = [];

        if (empty($filters['withDeleted'])) {
            $where[] = 'deleted_at IS NULL';
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR author LIKE :q OR isbn LIKE :q)';
            $params[':q'] = '%'.$filters['q'].'%';
        }
        if (!empty($filters['author'])) {
            $where[] = 'author LIKE :author';
            $params[':author'] = '%'.$filters['author'].'%';
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        // count
        $st = $pdo->prepare("SELECT COUNT(*) FROM books $whereSql");
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        // paging
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        // data
        $sql = "SELECT * FROM books $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll() ?: [];

        return [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPage' => (int)ceil($total / $perPage),
        ];
    }

    public function update(int $id, array $data): bool
    {
        $pdo = $this->db->connect();

        $allowed = ['title','author','isbn','price','description','published_at','total_copies','available_copies'];
        $set = [];
        $params = [':id' => $id];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = "$key = :$key";
                $params[":$key"] = ($key === 'price')
                    ? (float)$data[$key]
                    : $data[$key];
            }
        }

        if (!$set) return true;
        if (isset($params[':total_copies']) && isset($params[':available_copies'])) {
            $params[':available_copies'] = max(0, min((int)$params[':available_copies'], (int)$params[':total_copies']));
        }

        $sql = 'UPDATE books SET '.implode(', ', $set).', updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND deleted_at IS NULL';
        $st = $pdo->prepare($sql);
        return $st->execute($params);
    }

    public function delete(int $id): bool
    {
        $pdo = $this->db->connect();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $st = $pdo->prepare('UPDATE books SET deleted_at = :now WHERE id = :id AND deleted_at IS NULL');
        return $st->execute([':now' => $now, ':id' => $id]);
    }

    public function restore(int $id): bool
    {
        $pdo = $this->db->connect();
        $st = $pdo->prepare('UPDATE books SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL');
        return $st->execute([':id' => $id]);
    }

    public function forceDelete(int $id): bool
    {
        $pdo = $this->db->connect();
        $st = $pdo->prepare('DELETE FROM books WHERE id = :id');
        return $st->execute([':id' => $id]);
    }

    /**
     * Borrow book
     */
    public function borrow(int $userId, int $bookId, ?string $dueDate = null): int
    {
        $pdo = $this->db->connect();
        $pdo->beginTransaction();
        try {
            // Lock book
            $st = $pdo->prepare('SELECT id, available_copies FROM books WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $st->execute([$bookId]);
            $book = $st->fetch();
            if (!$book) throw new RuntimeException('Book not found');

            if ((int)$book['available_copies'] <= 0) {
                throw new RuntimeException('No copies available');
            }

            // Prevent duplicate active loan
            $chk = $pdo->prepare('SELECT id FROM loans WHERE user_id = ? AND book_id = ? AND returned_at IS NULL FOR UPDATE');
            $chk->execute([$userId, $bookId]);
            if ($chk->fetch()) {
                throw new RuntimeException('Already borrowed');
            }

            // due date
            $due = $dueDate
                ? $dueDate
                : (new DateTimeImmutable())->add(new DateInterval('P'.self::DEFAULT_LOAN_DAYS.'D'))->format('Y-m-d');

            // Insert loan
            $ins = $pdo->prepare('INSERT INTO loans (user_id, book_id, due_date) VALUES (:uid, :bid, :due)');
            $ins->execute([':uid' => $userId, ':bid' => $bookId, ':due' => $due]);
            $loanId = (int)$pdo->lastInsertId();

            // Decrement stock
            $upd = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?');
            $upd->execute([$bookId]);

            $pdo->commit();
            return $loanId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Return book
     */
    public function returnBook(int $userId, int $bookId): bool
    {
        $pdo = $this->db->connect();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('
                SELECT id FROM loans
                WHERE user_id = ? AND book_id = ? AND returned_at IS NULL
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ');
            $st->execute([$userId, $bookId]);
            $loan = $st->fetch();
            if (!$loan) throw new RuntimeException('No active loan');

            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            $updLoan = $pdo->prepare('UPDATE loans SET returned_at = :now WHERE id = :id');
            $updLoan->execute([':now' => $now, ':id' => $loan['id']]);

            $updBook = $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = :bid');
            $updBook->execute([':bid' => $bookId]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Renew book
     */
    public function renew(int $userId, int $bookId, int $days = 7): bool
    {
        $pdo = $this->db->connect();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('
                SELECT id, due_date FROM loans
                WHERE user_id = ? AND book_id = ? AND returned_at IS NULL
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ');
            $st->execute([$userId, $bookId]);
            $loan = $st->fetch();
            if (!$loan) throw new RuntimeException('No active loan to renew');

            $base = $loan['due_date'] ?: (new DateTimeImmutable())->format('Y-m-d');
            $newDue = (new DateTimeImmutable($base))
                ->add(new DateInterval('P'.max(1, $days).'D'))
                ->format('Y-m-d');

            $upd = $pdo->prepare('UPDATE loans SET due_date = :due WHERE id = :id');
            $ok  = $upd->execute([':due' => $newDue, ':id' => $loan['id']]);

            $pdo->commit();
            return $ok;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** 
     * Borrowing history by userId
     */
    public function activeLoansByUser(int $userId): array
    {
        $pdo = $this->db->connect();
        $st = $pdo->prepare('
            SELECT l.*, b.title, b.author, b.isbn
            FROM loans l
            JOIN books b ON b.id = l.book_id
            WHERE l.user_id = ? AND l.returned_at IS NULL
            ORDER BY l.borrowed_at DESC
        ');
        $st->execute([$userId]);
        return $st->fetchAll() ?: [];
    }
}
