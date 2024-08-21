<?php

namespace TareqAS\Psym\Tests\Parser;

use Mockery;
use PHPUnit\Framework\TestCase;
use TareqAS\Psym\Parser\SqlParser;
use TareqAS\Psym\Util\DoctrineEM;

class SqlParserTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testParserForSQL()
    {
        $sql = <<<SQL
UPDATE table1 AS t1
SET t1.c2 = null
WHERE t1.c1 in (
    SELECT t2.c1
    FROM table2 AS t2,
        table3 t3,
        table4
    JOIN "table5" AS "t5" ON t5.c1 = t2.c1
    LEFT JOIN `table6` AS `t6` ON t6.c1 = t2.c2
    RIGHT JOIN [table7] AS [t7] ON t7.c1 = t2.c3
    WHERE t2.c4 is not null
)
SQL;
        $expected = [
            ['table' => 'table1', 'alias' => 't1'],
            ['table' => 'table2', 'alias' => 't2'],
            // ['table' => 'table3', 'alias' => 't3'],
            // ['table' => 'table4', 'alias' => ''],
            ['table' => 'table5', 'alias' => 't5'],
            ['table' => 'table6', 'alias' => 't6'],
            ['table' => 'table7', 'alias' => 't7'],
        ];

        $parser = new SqlParser($sql, 'SQL');
        $tables = $parser->getTables();

        self::assertSame($expected, $tables);
    }

    public function testParserForDQL()
    {
        $sql = <<<DQL
SELECT t1.c1
FROM App\Entity\Table1 t1
JOIN t1.c2 t2 WITH t2.c1 = t1.c1
LEFT JOIN t1.c3 t3
WHERE t2.c2 IS NOT NULL
DQL;
        $expected = [
            ['table' => 'App\Entity\Table1', 'alias' => 't1'],
            ['table' => 'App\Entity\Table2', 'alias' => 't2'],
            ['table' => 'App\Entity\Table3', 'alias' => 't3'],
        ];

        $getEntitiesMappings = [
            'App\Entity\Table1' => [
                'table' => 'table1',
                'properties' => [
                    'c1' => ['column' => 'c1', 'targetEntity' => '', 'type' => 'string', 'default' => null],
                    'c2' => ['column' => 'c2', 'targetEntity' => 'App\Entity\Table2', 'type' => 'string', 'default' => null],
                    'c3' => ['column' => 'c3', 'targetEntity' => 'App\Entity\Table3', 'type' => 'string', 'default' => null],
                ],
            ],
            'App\Entity\Table2' => [
                'table' => 'table2',
                'properties' => [
                    'c1' => ['column' => 'c1', 'targetEntity' => '', 'type' => 'string', 'default' => null],
                    'c2' => ['column' => 'c2', 'targetEntity' => '', 'type' => 'string', 'default' => null],
                ],
            ],
            'App\Entity\Table3' => [
                'table' => 'table3',
                'properties' => [],
            ],
        ];

        $mock = Mockery::mock('alias:'.DoctrineEM::class);
        $mock->shouldReceive('findEntityFullName')
            ->andReturn('App\Entity\Table1');
        $mock->shouldReceive('getEntitiesMappings')
            ->andReturn($getEntitiesMappings);

        $parser = new SqlParser($sql, 'DQL');
        $tables = $parser->getTables();

        self::assertSame($expected, $tables);
    }
}
