<?php

use App\Models\AdminOperationLog;
use App\Models\BlackList;
use App\Models\BlackListLog;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorLog;
use App\Models\VisitorStatistics;

return [
    // New writable models require an explicit safe snapshot policy.
    'models' => [
        BlackList::class => ['type' => 'black_list', 'label' => '黑名单', 'fields' => ['ip']],
        User::class => ['type' => 'user', 'label' => '用户', 'fields' => ['name']],
    ],
    // Infrastructure writes are not administrator business operations.
    'excluded' => [
        AdminOperationLog::class,
        Visitor::class,
        VisitorLog::class,
        VisitorStatistics::class,
        BlackListLog::class,
    ],
];
