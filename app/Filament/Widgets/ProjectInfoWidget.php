<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ProjectInfoWidget extends Widget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected static string $view = 'filament.widgets.project-info-widget';
}
