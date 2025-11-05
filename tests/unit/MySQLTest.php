<?php

use PHPUnit\Framework\TestCase;
use Tests\MySQL\Request;

final class MySQLTest extends TestCase
{
    public function testOneToManyRequest(): void
    {
        $expected = 'SELECT T0.`id` AS T0_id,T0.`name` AS T0_name,T1.`id` AS T1_id,T2.`id` AS T2_id,T2.`name` AS T2_name
FROM `SyraTest`.`User` T0
LEFT JOIN `SyraTest`.`Access` T1 ON (T0.`id`=T1.`user`)
LEFT JOIN `SyraTest`.`Group` T2 ON (T1.`group`=T2.`id`)
WHERE T0.`id`=? OR T0.`id`=?
ORDER BY T0.`id` ASC';

        $request = Request::get('User')->withFields('id', 'name')
            ->leftJoin('Access', 'Accesses')->on('User', 'id', 'user')->withFields('id')
            ->leftJoin('Group')->on('Access', 'group')->withFields('id', 'name')
            ->where('', 'User', 'id', '=', 1)
            ->where('OR', 'User', 'id', '=', 2);

        $this->assertSame($expected, $request->generateDataSQL());
    }

    public function testMultipleOneToManyRequest(): void
    {
        $expected = 'SELECT T0.`id` AS T0_id,T1.`id` AS T1_id,T1.`name` AS T1_name,T2.`id` AS T2_id,T2.`name` AS T2_name
FROM `SyraTest`.`Audit` T0
LEFT JOIN `SyraTest`.`Group` T1 ON (T1.`id`=T1.`modelID` AND (T0.`model`=?))
LEFT JOIN `SyraTest`.`User` T2 ON (T2.`id`=T2.`modelID` AND (T0.`model`=?))
ORDER BY T0.`id` ASC';

        $request = Request::get('Audit')->withFields('id')
            ->leftJoin('Group', 'Groups')->on('Group', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'Group')->withFields('id', 'name')
            ->leftJoin('User', 'Users')->on('User', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'User')->withFields(...['id', 'name']);

        $this->assertSame($expected, $request->generateDataSQL());
    }

    public function testClosingParenthesis(): void
    {
        $expected = 'SELECT T0.`id` AS T0_id
FROM `SyraTest`.`Audit` T0
WHERE (T0.`id` IS NOT NULL)
ORDER BY T0.`id` ASC';

        $request = Request::get('Audit')->withFields('id')
            ->where('(', 'Audit', 'id', 'IS NOT NULL', closing: ')');

        $this->assertSame($expected, $request->generateDataSQL());
    }
}
