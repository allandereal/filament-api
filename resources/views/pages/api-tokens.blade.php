<x-filament-panels::page>
    @if ($plainTextToken)
        <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
            <x-slot name="heading">
                Copy your new API token
            </x-slot>

            <x-slot name="description">
                It won't be shown again. Send it in the <code>Authorization: Bearer</code> header of your API requests.
            </x-slot>

            <div
                x-data="{ copied: false }"
                style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem"
            >
                <x-filament::input.wrapper style="flex: 1 1 20rem">
                    <x-filament::input
                        type="text"
                        readonly
                        :value="$plainTextToken"
                        aria-label="API token"
                        x-on:focus="$el.select()"
                        style="font-family: ui-monospace, monospace"
                    />
                </x-filament::input.wrapper>

                <x-filament::button
                    color="gray"
                    icon="heroicon-m-clipboard"
                    x-on:click="window.navigator.clipboard.writeText(@js($plainTextToken)); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-show="! copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </x-filament::button>

                <x-filament::button color="gray" wire:click="dismissToken">
                    Done
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
