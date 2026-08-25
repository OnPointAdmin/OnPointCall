<div class="flex flex-wrap gap-2">
    @foreach ($items as $item)
        <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['count'] }}</span>
        </div>
    @endforeach
</div>
