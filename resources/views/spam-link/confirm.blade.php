@extends('layouts.app')

@section('content')
<x-spam-link-card>
    <div class="wf-head">
        <h1 class="wf-title">{{ __('spam_link.confirm.title.'.$action->value) }}</h1>
        <p class="wf-sub">{{ __('spam_link.entry', ['id' => $withdrawal->id]) }}</p>
    </div>

    <p class="wf-success-text" style="margin-left:0">
        {{ __('mail.field.datetime') }}: @include('mail.withdrawal.received-at', ['at' => $withdrawal->created_at])
    </p>
    <p class="wf-success-text" style="margin-left:0">
        {{ __('spam_link.status') }}: <strong>{{ ($withdrawal->spam_verdict ?? $withdrawal->spam_level)->getLabel() }}</strong>
    </p>

    @if ($open)
        <p class="wf-success-text" style="margin-left:0">{{ __('spam_link.confirm.text.'.$action->value, ['days' => \App\Models\Withdrawal::SPAM_RETENTION_DAYS]) }}</p>
        <form method="POST" action="{{ $formAction }}" class="wf-actions">
            @csrf
            <button type="submit" class="wf-btn">{{ __('spam_link.confirm.button.'.$action->value) }}</button>
        </form>
    @else
        <p class="wf-success-text" style="margin-left:0">{{ __('spam_link.closed') }}</p>
    @endif
</x-spam-link-card>
@endsection
