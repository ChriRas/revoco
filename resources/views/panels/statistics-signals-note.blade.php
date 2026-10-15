{{--
    Footer row of the per-signal table: how the counts relate to the withdrawals list.
--}}
<td colspan="{{ count($columns) }}" style="padding: 0.75rem 1rem; font-size: 0.875rem; color: var(--gray-500);">
    {{ __('panel.statistics.signals.note', ['days' => \App\Models\Withdrawal::SPAM_RETENTION_DAYS]) }}
</td>
