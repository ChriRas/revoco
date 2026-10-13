@extends('layouts.app')

@section('content')
<x-spam-link-card>
    <h1 class="wf-title">{{ __('spam_link.gone.title') }}</h1>
    <p class="wf-sub">{{ __('spam_link.gone.text') }}</p>
</x-spam-link-card>
@endsection
