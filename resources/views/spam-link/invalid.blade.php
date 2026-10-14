@extends('layouts.app')

@section('content')
<x-spam-link-card>
    <h1 class="wf-title">{{ __('spam_link.invalid.title') }}</h1>
    <p class="wf-sub">{{ __('spam_link.invalid.text') }}</p>
</x-spam-link-card>
@endsection
