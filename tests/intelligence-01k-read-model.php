<?php
/**
 * INTELLIGENCE-01K: standalone, read-only contract regression tests.
 * Run: php tests/intelligence-01k-read-model.php
 * No WordPress bootstrap, database, HTTP, or production writes.
 */
declare(strict_types=1);

function wp_timezone(): DateTimeZone { return new DateTimeZone('Europe/Rome'); }

class BadAround_Discovery_Query {
    private $provider;
    public $calls = array();
    public function __construct(callable $provider) { $this->provider = $provider; }
    public function discover($args) {
        $this->calls[] = $args;
        return ($this->provider)($args, count($this->calls));
    }
}

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/wordpress/plugins/badaround-core/includes/class-badaround-intelligence-read-model.php';

function assert_true($actual, $message) {
    if (!$actual) { throw new RuntimeException($message); }
}
function assert_throws(callable $call, $message) {
    try { $call(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('Expected fail closed: ' . $message);
}
function entry($id, $type, $territory, $date) {
    return array('id'=>$id,'event_type'=>array('name'=>$type),
        'territory'=>array('name'=>$territory),'occurred_date'=>$date);
}
function page($items, $number = 1, $pages = 1, $total = null) {
    return array('items'=>$items,'page'=>$number,'pages'=>$pages,
        'total'=>$total === null ? count($items) : $total);
}

$cases = 0;
$run = function ($label, callable $test) use (&$cases) {
    $test(); $cases++; echo 'PASS ' . $label . PHP_EOL;
};

$run('aggregation, terminology, and two-pass read', function () {
    $source = page(array(entry(1,'Furto','Roma','2020-10-20'),
        entry(2,'Furto','Roma','20/10/2020'),
        entry(3,'Avvistamento','Pomezia','2035-01-01')));
    $query = new BadAround_Discovery_Query(function ($args) use ($source) {return $source;});
    $result = BadAround_Intelligence_Read_Model::snapshot($query);
    assert_true($result['report_count'] === 3, 'Report count');
    assert_true($result['occurred_date_available'] === 2, 'Valid historical dates only');
    assert_true($result['by_event_type'] === array('Avvistamento'=>1,'Furto'=>2), 'Type counts');
    assert_true($result['by_territory_label'] === array('Pomezia'=>1,'Roma'=>2), 'Territory counts');
    assert_true($result['unit'] === 'published_reports_not_unique_incidents', 'No incident inference');
    assert_true($result['public_alerts'] === array(), 'No unreviewed alerts');
    assert_true($result['certified_episode_count'] === false, 'No certified episode claim');
    assert_true($result['transactional_snapshot'] === false, 'No transactional guarantee');
    assert_true(count($query->calls) === 2, 'Must re-read for drift');
    foreach ($query->calls as $args) {
        assert_true($args['period'] === 0 && $args['per_page'] === 100, 'Canonical unfiltered Discovery');
    }
});

$run('duplicate IDs fail closed', function () {
    $query = new BadAround_Discovery_Query(function ($args) {
        return page(array(entry(7,'Furto','Roma','2020-01-01')),
            $args['page'],2,2);
    });
    assert_throws(function () use ($query) {
        BadAround_Intelligence_Read_Model::snapshot($query);
    }, 'Duplicate ID');
});

$run('incomplete pagination fails closed', function () {
    $query = new BadAround_Discovery_Query(function ($args) {
        return page(array(entry(3,'Furto','Roma','2020-01-01')),1,1,2);
    });
    assert_throws(function () use ($query) {
        BadAround_Intelligence_Read_Model::snapshot($query);
    }, 'Incomplete items');
});

$run('mutating second pass fails closed', function () {
    $query = new BadAround_Discovery_Query(function ($args, $call) {
        return page(array(entry($call === 1 ? 1 : 2,'Furto','Roma','2020-01-01')));
    });
    assert_throws(function () use ($query) {
        BadAround_Intelligence_Read_Model::snapshot($query);
    }, 'Snapshot drift');
});

$run('invalid canonical response fails closed', function () {
    $query = new BadAround_Discovery_Query(function ($args) {return array('items'=>array());});
    assert_throws(function () use ($query) {
        BadAround_Intelligence_Read_Model::snapshot($query);
    }, 'Malformed Discovery');
});

$run('empty snapshot stays empty and makes no risk claim', function () {
    $query = new BadAround_Discovery_Query(function ($args) {return page(array());});
    $result = BadAround_Intelligence_Read_Model::snapshot($query);
    assert_true($result['report_count'] === 0, 'Empty count');
    assert_true($result['by_event_type'] === array(), 'No types');
    assert_true($result['public_alerts'] === array(), 'No alerts');
});

echo 'INTELLIGENCE-01K: ' . $cases . ' isolated contract checks passed.' . PHP_EOL;
