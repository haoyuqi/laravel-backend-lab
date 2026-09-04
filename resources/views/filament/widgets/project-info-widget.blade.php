<x-filament-widgets::widget class="fi-project-info-widget">
    <x-filament::section>
        <div class="flex items-center justify-between gap-x-4">
            <div class="flex items-center gap-x-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400">
                    <x-filament::icon
                        icon="heroicon-m-code-bracket-square"
                        class="h-6 w-6"
                    />
                </div>

                <div class="flex items-center gap-x-2">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Laravel Backend Lab
                    </h2>
                    @if(config('app.version'))
                        <x-filament::badge color="gray" size="sm">
                            v{{ config('app.version') }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-x-2">
                <x-filament::button
                    color="gray"
                    icon="heroicon-m-arrow-top-right-on-square"
                    tag="a"
                    href="https://github.com/haoyuqi/laravel-backend-lab"
                    target="_blank"
                    size="sm"
                >
                    GitHub 源码
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
