<x-filament-panels::page>
    <div x-on:close-modal.window="if ($event.detail?.id === 'import-check-disable-confirm') $wire.set('pendingDisableImportCheck', null)">
        {{ $this->content }}

        <x-filament::modal
            id="import-check-disable-confirm"
            alert
            width="md"
            heading="Are you sure?"
            description="Disabling this check means imported leads will skip this screening step. You can run it later from the batch report."
            :close-by-clicking-away="true"
            :close-by-escaping="true"
        >
            <x-slot name="footer">
                <div class="fi-modal-footer-actions">
                    <x-filament::button color="gray" wire:click="cancelDisableImportCheck">
                        Keep enabled
                    </x-filament::button>

                    <x-filament::button color="danger" wire:click="confirmDisableImportCheck">
                        Yes, disable
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
