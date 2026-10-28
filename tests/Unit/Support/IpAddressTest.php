<?php

namespace Tests\Unit\Support;

use App\Models\BlackList;
use App\Support\IpAddress;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\LazyCollection;
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

    public function test_non_postgres_blacklist_lookup_checks_equivalent_forms_and_scans_ipv6_candidates(): void
    {
        $candidate = new BlackList(['ip' => '2001:0db8:0:0:0:0:0:1']);
        $other = new BlackList(['ip' => '2001:db8::9']);
        $connection = \Mockery::mock(Connection::class)->makePartial();
        $connection->shouldReceive('getDriverName')->times(3)->andReturn('sqlite');
        $model = \Mockery::mock(BlackList::class)->makePartial();
        $model->shouldReceive('getConnection')->times(3)->andReturn($connection);

        $builder = \Mockery::mock(Builder::class, [\Mockery::mock(QueryBuilder::class)])->makePartial();
        $builder->shouldReceive('getModel')->once()->andReturn($model);
        $builder->shouldReceive('whereIn')->once()->with('ip', IpAddress::equivalentForms('2001:db8::1'))->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn(null);
        $builder->shouldReceive('where')->once()->with('ip', 'like', '%:%')->andReturnSelf();
        $builder->shouldReceive('cursor')->once()->andReturn(LazyCollection::make([$other, $candidate]));

        $this->assertSame($candidate, IpAddress::findBlacklistRecord($builder, '2001:db8::1'));

        $emptyBuilder = \Mockery::mock(Builder::class, [\Mockery::mock(QueryBuilder::class)])->makePartial();
        $emptyBuilder->shouldReceive('getModel')->once()->andReturn($model);
        $emptyBuilder->shouldReceive('whereIn')->once()->with('ip', IpAddress::equivalentForms('2001:db8::2'))->andReturnSelf();
        $emptyBuilder->shouldReceive('first')->once()->andReturn(null);
        $emptyBuilder->shouldReceive('where')->once()->with('ip', 'like', '%:%')->andReturnSelf();
        $emptyBuilder->shouldReceive('cursor')->once()->andReturn(LazyCollection::make([$other]));

        $this->assertNull(IpAddress::findBlacklistRecord($emptyBuilder, '2001:db8::2'));

        $ipv4Builder = \Mockery::mock(Builder::class, [\Mockery::mock(QueryBuilder::class)])->makePartial();
        $ipv4Builder->shouldReceive('getModel')->once()->andReturn($model);
        $ipv4Builder->shouldReceive('whereIn')->once()->with('ip', IpAddress::equivalentForms('198.51.100.1'))->andReturnSelf();
        $ipv4Builder->shouldReceive('first')->once()->andReturn(null);

        $this->assertNull(IpAddress::findBlacklistRecord($ipv4Builder, '198.51.100.1'));
    }
}
