@php
    use NetOs\Vitals\Dashboard\Health;
    use NetOs\Vitals\Support\Formatter;
    /** @var \NetOs\Vitals\Dashboard\TenantSnapshot $snapshot */
    $tenant = $snapshot->tenant;
    $applicable = $snapshot->applicable();
    $alert = $snapshot->sorted()->first(fn ($check) => $check->health->isFailing() && ($check->state?->consecutive_failures ?? 0) > 1);
@endphp
@extends('vitals::layout')

@section('title', $tenant->name)

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('vitals.overview') }}">Overview</a><span>/</span><span>{{ $tenant->name }}</span></nav>
    <div class="pagehead" style="margin-top:10px">
        <div>
            <h2 class="page">{{ $tenant->name }}</h2>
            <div class="facts">
                @if($snapshot->isUnreachable())
                    @include('vitals::partials.badge', ['tone' => 'fail', 'label' => 'Unreachable'])
                @else
                    @if($snapshot->failing() > 0) @include('vitals::partials.badge', ['tone' => 'fail', 'label' => $snapshot->failing().' failing']) @endif
                    @if($snapshot->count(Health::Warning) > 0) @include('vitals::partials.badge', ['tone' => 'warn', 'label' => $snapshot->count(Health::Warning).' '.str('warning')->plural($snapshot->count(Health::Warning))]) @endif
                    @if($snapshot->count(Health::Stale) > 0) @include('vitals::partials.badge', ['tone' => 'idle', 'label' => $snapshot->count(Health::Stale).' stale']) @endif
                    @if(! $snapshot->needsAttention()) @include('vitals::partials.badge', ['tone' => $snapshot->health()->tone(), 'label' => $snapshot->statusText()]) @endif
                @endif
                @if($tenant->domain)<span class="badge b-plain">{{ $tenant->domain }}</span>@endif
                <span class="badge b-plain">{{ $applicable->count() }} {{ str('check')->plural($applicable->count()) }}</span>
            </div>
        </div>
        <div class="actions"><button class="btn primary" type="button" data-run="{{ route('vitals.tenant.run', $tenant->key) }}">Run all checks</button></div>
    </div>

    @if($snapshot->isUnreachable())
        <div class="alert" role="status"><span class="dotsq q-fail"></span><span><strong>{{ $tenant->name }}</strong> could not be initialized, so none of its checks ran. {{ $applicable->first()?->summary() }}</span></div>
    @elseif($alert)
        <div class="alert" role="status"><span class="dotsq q-fail"></span><span><strong>{{ $alert->check->label() }}</strong> has failed {{ $alert->state->consecutive_failures }} times in a row since {{ Formatter::dateTime($alert->state->status_since) }}.
            @if($alert->state->last_success_at) The last successful run was {{ Formatter::dateTime($alert->state->last_success_at) }}. @else It has not succeeded in the stored history. @endif</span></div>
    @endif

    <div class="sect" style="margin-top:24px">
        @if($snapshot->checks->isEmpty())
            <div class="card empty">No tenant checks registered. Add them with <code>Vitals::tenant([...])</code>.</div>
        @endif
        <div class="checkgrid">
            @foreach($snapshot->sorted() as $check)
                @php $url = route('vitals.check', ['check' => $check->check->key(), 'tenant' => $tenant->key]); @endphp
                @if($check->health === Health::NotApplicable)
                    <div class="card cc na">
                        <div class="cc-top"><a href="{{ $url }}">{{ $check->check->label() }}<small>{{ $check->check->key() }}</small></a>@include('vitals::partials.badge', ['tone' => 'idle', 'label' => 'Not applicable'])</div>
                        <p>{{ $check->summary() }}</p>
                    </div>
                @else
                    <div class="card cc" data-last-run="{{ $check->lastRunAt()?->getTimestamp() }}">
                        <div class="cc-top"><a href="{{ $url }}">{{ $check->check->label() }}<small>{{ $check->check->key() }}</small></a>@include('vitals::partials.badge', ['tone' => $check->health->tone(), 'label' => $check->health->label()])</div>
                        <p>{{ $check->summary() }}</p>
                        <div class="cc-foot">
                            @include('vitals::partials.runs', ['snapshot' => $check])
                            <span class="ago">{{ Formatter::ago($check->lastRunAt(), $now) }}</span>
                            <button class="linkbtn" type="button" data-run="{{ route('vitals.check.run', ['check' => $check->check->key(), 'tenant' => $tenant->key]) }}">Run now</button>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
