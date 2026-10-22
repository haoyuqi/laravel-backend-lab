<?php

namespace Tests\Unit\Support;

use App\Models\BlackList;
use App\Support\IpAddress;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Tests\TestCase;

class IpAddressTest extends TestCase
{
    public function test_postgres_blacklist_lookup_uses_inet_comparison_with_canonical_ip(): void
    {
        $record = new BlackList;
        $connection = \Mockery::mock(Connection::class)->makePartial();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $model = \Mockery::mock(BlackList::class)->makePartial();
        $model->shouldReceive('getConnection')->once()->andReturn($connection);
        $grammar = new PostgresGrammar($connection);
        $query = \Mockery::mock(QueryBuilder::class)->makePartial();
        $query->shouldReceive('getGrammar')->once()->andReturn($grammar);
        $builder = \Mockery::mock(Builder::class)->makePartial();
        $builder->shouldReceive('getModel')->once()->andReturn($model);
        $builder->shouldReceive('getQuery')->once()->andReturn($query);
        $builder->shouldReceive('whereRaw')->once()->with('"ip" = ?::inet', ['2001:db8::1'])->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn($record);

        $this->assertSame($record, IpAddress::findBlacklistRecord($builder, '2001:0db8:0:0:0:0:0:1'));
    }
}
