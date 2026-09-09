<x-filament-widgets::widget class="fi-health-status-widget">
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-x-2">
                    <x-filament::icon
                        icon="heroicon-m-heart"
                        class="h-5 w-5 text-gray-500 dark:text-gray-400"
                    />
                    <span class="text-base font-semibold text-gray-950 dark:text-white">基础设施与服务健康</span>
                </div>
                <div>
                    @if($isHealthy)
                        <x-filament::badge color="success" size="sm" icon="heroicon-m-check-circle">
                            服务正常
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="danger" size="sm" icon="heroicon-m-exclamation-triangle">
                            注意检查
                        </x-filament::badge>
                    @endif
                </div>
            </div>
        </x-slot>

        <x-filament::grid :default="1" :sm="2" :lg="4" class="gap-3">
            @foreach($cards as $card)
                <div class="flex items-center justify-between gap-x-2 rounded-lg border border-gray-200/70 bg-gray-50/50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center gap-x-3 min-w-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                            <x-filament::icon :icon="$card['icon']" class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $card['title'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $card['subtitle'] }}
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <x-filament::badge :color="$card['badge_color']" size="sm" :icon="$card['badge_icon']">
                            {{ $card['badge_text'] }}
                        </x-filament::badge>
                    </div>
                </div>
            @endforeach
        </x-filament::grid>
    </x-filament::section>
</x-filament-widgets::widget>
