@php
    use NetOs\Vitals\Dashboard\Health;
    use NetOs\Vitals\Support\Formatter;
    /** @var \NetOs\Vitals\Dashboard\CheckAcrossTenants $matrix */
    $check = $matrix->check;
    $mute = $matrix->allTenantsMute;
@endphp
@extends('vitals::layout')

@section('title', $check->label().' · all tenants')

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('vitals.overview') }}">Overview</a><span>/</span><span>{{ $check->label() }}</span></nav>
    <div class="pagehead" style="margin-top:10px">
        <div>
            <h2 class="page">{{ $check->label() }} <span class="sub-title">for all tenants</span></h2>
            <div class="facts">
                @if($matrix->failing() > 0)@include('vitals::partials.badge', ['tone' => 'fail', 'label' => $matrix->failing().' failing'])@endif
                @if($matrix->count(Health::Warning) > 0)@include('vitals::partials.badge', ['tone' => 'warn', 'label' => $matrix->count(Health::Warning).' '.str('warning')->plural($matrix->count(Health::Warning))])@endif
                @if($matrix->count(Health::Stale) > 0)@include('vitals::partials.badge', ['tone' => 'idle', 'label' => $matrix->count(Health::Stale).' stale'])@endif
                @if($matrix->muted() > 0)<span class="badge b-mute">{{ $matrix->muted() }} muted</span>@endif
                @if($mute)<span class="badge b-mute" title="{{ $mute->description() }}">Muted for all tenants {{ $mute->durationLabel() }}</span>@endif
                <span class="badge b-plain">{{ $check->key() }}</span>
                <span class="badge b-plain">{{ $check->frequency->label() }}</span>
                <span class="badge b-plain">{{ $matrix->applicable() }} of {{ $matrix->snapshots->count() }} {{ str('tenant')->plural($matrix->snapshots->count()) }}</span>
                @if($check->check->runbookUrl())<a class="badge b-link" href="{{ $check->check->runbookUrl() }}" target="_blank" rel="noopener">Runbook ↗</a>@endif
            </div>
        </div>
        <div class="actions">
            @if($mute)
                <button class="btn" type="button" data-unmute="{{ route('vitals.mutes.destroy', $mute->id) }}" title="{{ $mute->description() }}">Unmute for all tenants</button>
            @else
                <button class="btn" type="button" data-mute="{{ json_encode(['scope' => 'tenant', 'check' => $check->key(), 'tenant' => null, 'title' => $check->label().' for all tenants', 'allTenants' => false]) }}">Mute for all tenants</button>
            @endif
        </div>
    </div>

    <div class="sect" style="margin-top:24px">
        <div class="card list">
            @forelse($matrix->sorted() as $snapshot)
                <div class="xrow {{ $snapshot->health === Health::NotApplicable ? 'na' : '' }}" data-last-run="{{ $snapshot->lastRunAt()?->getTimestamp() }}">
                    <a href="{{ $snapshot->url() }}"><b>{{ $snapshot->tenant->name }}</b><small>{{ $snapshot->tenant->domain ?? $snapshot->tenant->key }}</small></a>
                    @include('vitals::partials.status-badges', ['check' => $snapshot])
                    <span class="summary">{{ $snapshot->summary() }}</span>
                    <span class="ago">{{ $snapshot->health === Health::NotApplicable ? '' : Formatter::ago($snapshot->lastRunAt(), $now) }}</span>
                    <span class="linkbtns">
                        @if($snapshot->health !== Health::NotApplicable)
                            @include('vitals::partials.mute-button', ['check' => $snapshot, 'class' => 'linkbtn'])
                            <button class="linkbtn" type="button" data-run="{{ route('vitals.check.run', ['check' => $check->key(), 'tenant' => $snapshot->tenant->key]) }}">Run now</button>
                        @endif
                    </span>
                </div>
            @empty
                <div class="empty">There are no tenants yet.</div>
            @endforelse
        </div>
    </div>
@endsection
