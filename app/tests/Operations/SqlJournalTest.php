<?php

declare(strict_types=1);

namespace App\Tests\Operations;

use App\Operations\Sql\SqlJournal;
use PHPUnit\Framework\TestCase;

final class SqlJournalTest extends TestCase
{
    public function testTemplateKeepsIdentifiersAndMasksPostgresLiterals(): void
    {
        $sql = <<<'SQL'
            SELECT "name--with punctuation", e'private\'text', $tag$hidden$tag$, 42.5e-2, 0xCAFE, $1, ? /* private /* nested */ more */ -- comment
            FROM source2 WHERE note = 'hidden ''literal'''
            SQL;

        self::assertSame('SELECT "name--with punctuation", ?, ?, ?, ?, $1, ?'."    \n".'FROM source2 WHERE note = ?', SqlJournal::template($sql));
        self::assertSame('Шаблон запроса недоступен', SqlJournal::template("SELECT 'incomplete"));
    }
}
