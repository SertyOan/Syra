<?php

use PHPUnit\Framework\TestCase;
use Tests\MSSQL\Request;

final class MSSQLTest extends TestCase
{
    public function testOneToManyRequest(): void
    {
        $expected = 'SELECT DISTINCT T0.[id] AS T0_id,T1.[id] AS T1_id,T1.[name] AS T1_name,T2.[id] AS T2_id,T2.[name] AS T2_name
FROM [SyraTest].[Audit] T0
LEFT JOIN [SyraTest].[Group] T1 ON (T1.[id]=T1.[modelID] AND (T0.[model]=?))
LEFT JOIN [SyraTest].[User] T2 ON (T2.[id]=T2.[modelID] AND (T0.[model]=?))
ORDER BY T0.[id] ASC';

        $request = Request::get('Audit')->withFields('id')
            ->leftJoin('Group', 'Groups')->on('Group', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'Group')->withFields('id', 'name')
            ->leftJoin('User', 'Users')->on('User', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'User')->withFields(...['id', 'name']);

        $this->assertSame($expected, $request->generateDataSQL());
    }
}
