{{--
    Per-source status of the downloaded network data (meta.json): last refresh and entry count.
--}}
@php
    $meta = app(\App\Network\NetworkData::class)->meta();
@endphp
<div class="space-y-2">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.settings.network.status.help') }}</p>
    <table class="w-full text-left text-sm">
        <thead>
            <tr>
                <th class="py-1 pe-4 font-medium">{{ __('panel.settings.network.status.heading') }}</th>
                <th class="py-1 pe-4 font-medium">{{ __('panel.settings.network.status.last_refresh') }}</th>
                <th class="py-1 font-medium">{{ __('panel.settings.network.status.entries') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Network\NetworkDataRefresher::SOURCES as $source)
                @php
                    $row = $meta[$source] ?? null;
                    $fetchedAt = is_array($row) && isset($row['fetched_at']) ? \Illuminate\Support\Carbon::parse($row['fetched_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') : null;
                @endphp
                <tr>
                    <td class="py-1 pe-4">{{ __('panel.settings.network.status.source.'.$source) }}</td>
                    <td class="py-1 pe-4">{{ $fetchedAt ?? __('panel.settings.network.status.never') }}</td>
                    <td class="py-1">{{ is_array($row) && isset($row['records']) ? number_format((int) $row['records'], 0, ',', '.') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
