{{--
    Live preview above the withdrawal list (slice-031). Server-rendered with the tab's newest row; Alpine swaps
    in the hovered or keyboard-focused table row from the embedded page data (no request) and falls back to
    the default once the pointer or focus leaves the rows. Rows are found by Filament's row
    wire:key "<component>.table.records.<id>".

    The wire:key changes with the data, so every Livewire re-render (tab, page, filter, search) replaces the
    element and Alpine starts over on the current rows.

    @param array<string, mixed>|null      $default  WithdrawalPreview::data() of the tab's newest row
    @param array<int, array<string, mixed>> $rows   WithdrawalPreview::data() of the current page, keyed by id
--}}
@php
    $fields = [
        'received' => __('panel.infolist.field.received_at'),
        'name' => __('panel.infolist.field.name'),
        'email' => __('panel.infolist.field.email'),
        'order' => __('panel.infolist.field.order_number'),
    ];
@endphp
<div
    wire:key="withdrawal-preview-{{ md5((string) json_encode([$default, $rows], JSON_INVALID_UTF8_SUBSTITUTE)) }}"
    class="wf-preview"
    data-testid="withdrawal-preview"
    x-data="{
        rows: @js((object) $rows),
        fallback: @js($default),
        view: @js($default),
        rowId(target) {
            const row = target instanceof Element ? target.closest('tr.fi-ta-row[wire\\:key]') : null;
            const key = row?.getAttribute('wire:key') ?? '';
            const marker = '.table.records.';

            return key.includes(marker) ? key.slice(key.lastIndexOf(marker) + marker.length) : null;
        },
        point(target) {
            const id = this.rowId(target);
            this.view = (id !== null && this.rows[id]) ? this.rows[id] : this.fallback;
        },
    }"
    x-on:mouseover.document="point($event.target)"
    x-on:focusin.document="point($event.target)"
    x-on:focusout.document="if (! rowId($event.relatedTarget)) view = fallback"
>
    <x-filament::section compact>
        <x-slot name="heading">{{ __('panel.preview.heading') }}</x-slot>

        {{-- Fixed size: every field has a fixed number of lines (--lines), longer text is cut with an ellipsis
             (the full text is in the detail view). An empty tab keeps the same size: the grid stays, invisible. --}}
        <div class="wf-preview-body">
            <dl class="wf-preview-grid" @if ($default === null) style="visibility: hidden" aria-hidden="true" @endif>
                @foreach ($fields as $key => $label)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd style="--lines: {{ $key === 'received' ? 1 : 2 }}" x-text="view?.{{ $key }} ?? '—'">{{ $default[$key] ?? '—' }}</dd>
                    </div>
                @endforeach

                <div class="wf-preview-wide">
                    <dt>{{ __('panel.infolist.field.subject') }}</dt>
                    <dd style="--lines: 4" class="wf-preview-subject" x-text="view?.subject ?? '—'">{{ $default['subject'] ?? '—' }}</dd>
                </div>

                <div>
                    <dt>{{ __('panel.infolist.field.spam_level') }}</dt>
                    <dd class="wf-preview-badge">
                        @foreach (['success', 'warning', 'danger'] as $color)
                            <span
                                x-show="view?.levelColor === @js($color)"
                                @if (($default['levelColor'] ?? null) !== $color) style="display: none" @endif
                            >
                                <x-filament::badge :color="$color">
                                    <span x-text="view?.level">{{ $default['level'] ?? '' }}</span>
                                </x-filament::badge>
                            </span>
                        @endforeach
                    </dd>
                </div>

                <div class="wf-preview-reasons">
                    <dt>{{ __('panel.infolist.field.spam_reason') }}</dt>
                    <dd style="--lines: 3" x-text="view?.reasons ?? '—'">{{ $default['reasons'] ?? '—' }}</dd>
                </div>

                <div>
                    <dt>{{ __('panel.infolist.field.spam_verdict') }}</dt>
                    <dd style="--lines: 2" x-text="view?.decision ?? '—'">{{ $default['decision'] ?? '—' }}</dd>
                </div>
            </dl>

            @if ($default === null)
                <p class="wf-preview-empty">{{ __('panel.preview.empty') }}</p>
            @endif
        </div>
    </x-filament::section>
</div>

<style>
    .wf-preview { margin-bottom: 1.5rem; }
    .wf-preview-body { position: relative; }
    .wf-preview-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem 1.5rem;
        margin: 0;
    }
    @media (min-width: 64rem) {
        .wf-preview-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    .wf-preview-wide { grid-column: 1 / -1; }
    .wf-preview-reasons { grid-column: span 2; }
    .wf-preview-grid dt {
        font-size: 0.75rem;
        line-height: 1rem;
        color: var(--gray-500);
    }
    .wf-preview-grid dd {
        --lines: 1;
        margin: 0.125rem 0 0;
        font-size: 0.875rem;
        line-height: 1.25rem;
        height: calc(var(--lines) * 1.25rem);
        overflow: hidden;
        overflow-wrap: anywhere;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: var(--lines);
    }
    .wf-preview-grid dd.wf-preview-badge {
        display: block;
        height: 1.5rem;
    }
    .wf-preview-subject { white-space: pre-line; }
    .wf-preview-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-size: 0.875rem;
        color: var(--gray-500);
    }
</style>
