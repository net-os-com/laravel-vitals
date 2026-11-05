@php
    use NetOs\Vitals\Dashboard\Health;
    use NetOs\Vitals\Support\Formatter;
    /** @var \NetOs\Vitals\Dashboard\Overview $overview */
    $attention = $overview->attention();
    $tenantCount = $overview->tenants->count();
    $centralCount = $overview->central->count();
@endphp
@extends('vitals::layout')

@section('title', 'Overview')

@section('content')
    <div class="pagehead">
        <div>
            <div class="eyebrow">{{ $overview->environment }} · updated {{ Formatter::time($overview->now) }}</div>
            <h2 class="page">{{ $overview->headline() }}</h2>
            <p class="lede">
                @if($overview->usesTenancy){{ $tenantCount }} {{ str('tenant')->plural($tenantCount) }} and @endif{{ $centralCount }} central {{ str('check')->plural($centralCount) }}.
                @if($overview->heartbeat)
                    The scheduler last dispatched checks {{ Formatter::ago($overview->heartbeat, $overview->now) }}.
                @else
                    The scheduler has not dispatched any checks yet.
                @endif
            </p>
        </div>
        <div class="actions">
            <button class="btn" type="button" data-refresh>Refresh</button>
            <button class="btn primary" type="button" data-run="{{ route('vitals.run') }}">Run all checks</button>
        </div>
    </div>

    @if($overview->heartbeatStale)
        <div class="alert" role="status"><span class="dotsq q-fail"></span><span><strong>Checks are not being dispatched.</strong>
            @if($overview->heartbeat)
                The last dispatch was {{ Formatter::dateTime($overview->heartbeat) }}.
            @endif
            Make sure <code>php artisan schedule:run</code> runs every minute, because <code>vitals:dispatch</code> depends on it.</span></div>
    @endif

    <div class="stats">
        @foreach($overview->stats() as $stat)
            <div class="card stat t-{{ $stat['tone'] }}"><div class="v">{{ $stat['value'] }}</div><div class="k">{{ $stat['label'] }}</div></div>
        @endforeach
    </div>

    @if($overview->usesTenancy && $attention->isNotEmpty())
        <div class="sect">
            <div class="secthead"><h3>Needs attention</h3><span>Sorted by severity</span></div>
            <div class="attention">
                @foreach($attention as $tenant)
                    <a class="card att" href="{{ route('vitals.tenant', $tenant->tenant->key) }}">
                        <div class="att-top">
                            <div><b>{{ $tenant->tenant->name }}</b>@if($tenant->tenant->domain)<small>{{ $tenant->tenant->domain }}</small>@endif</div>
                            @include('vitals::partials.badge', ['tone' => $tenant->health()->tone(), 'label' => $tenant->badgeText()])
                        </div>
                        <ul>
                            @if($tenant->isUnreachable())
                                <li><span class="dotsq q-fail"></span><span class="msg">{{ $tenant->applicable()->first()?->summary() }}</span><span class="ago">{{ $tenant->applicable()->count() }} {{ str('check')->plural($tenant->applicable()->count()) }}</span></li>
                            @else
                                @foreach($tenant->problems() as $check)
                                    <li>
                                        <span class="dotsq q-{{ $check->health->tone() }}"></span>
                                        @if($check->health === Health::Stale)
                                            <span class="msg">{{ $check->check->label() }} · no result for {{ Formatter::duration($check->minutesWithoutResult($overview->now) * 60) }}</span>
                                            <span class="ago">expected {{ lcfirst($check->check->frequency->label()) }}</span>
                                        @else
                                            <span class="msg">{{ $check->check->label() }} · {{ $check->summary() }}</span>
                                            <span class="ago">{{ $check->health->isFailing() ? 'since '.Formatter::dateTime($check->state?->status_since) : Formatter::ago($check->lastRunAt(), $overview->now) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="sect">
        <div class="secthead"><h3>Central</h3><span>{{ $overview->centralHealthy() }} of {{ $centralCount }} OK</span></div>
        @if($overview->central->isEmpty())
            <div class="card empty">No central checks registered. Add them with <code>Vitals::central([...])</code>.</div>
        @else
            <div class="tiles">
                @foreach($overview->central->sortByDesc(fn ($check) => $check->health->severity()) as $check)
                    <a class="card tile" href="{{ route('vitals.check', $check->check->key()) }}" data-last-run="{{ $check->lastRunAt()?->getTimestamp() }}">
                        <div class="tile-top"><b>{{ $check->check->label() }}</b>@include('vitals::partials.badge', ['tone' => $check->health->tone(), 'label' => $check->health->label()])</div>
                        <p>{{ $check->summary() }}</p>
                        <div class="tile-foot">@include('vitals::partials.runs', ['snapshot' => $check])<span class="ago">{{ Formatter::ago($check->lastRunAt(), $overview->now) }}</span></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if($overview->usesTenancy)
        <div class="sect">
            <div class="secthead"><h3>All tenants</h3><span>{{ $tenantCount }} {{ str('tenant')->plural($tenantCount) }}</span></div>
            <div class="card list">
                <div class="list-tools">
                    <input class="search" type="search" placeholder="Search tenants" aria-label="Search tenants" data-search-input>
                    <div class="chips" role="group" aria-label="Filter">
                        <button type="button" class="chip on" data-filter="all">All</button>
                        <button type="button" class="chip" data-filter="problems">With problems</button>
                        <button type="button" class="chip" data-filter="healthy">Healthy</button>
                    </div>
                </div>
                @foreach($overview->tenants as $tenant)
                    <a class="trow" href="{{ route('vitals.tenant', $tenant->tenant->key) }}" data-tenant-row data-problem="{{ $tenant->needsAttention() ? 1 : 0 }}" data-search="{{ mb_strtolower($tenant->tenant->name.' '.$tenant->tenant->key.' '.$tenant->tenant->domain) }}">
                        <span><b>{{ $tenant->tenant->name }}</b><small>{{ $tenant->tenant->domain ?? $tenant->tenant->key }}</small></span>
                        <span class="dotline" aria-hidden="true">@foreach($tenant->checks as $check)<span class="dotsq q-{{ $check->health->tone() }}" title="{{ $check->check->label() }}: {{ $check->health->label() }}"></span>@endforeach</span>
                        <span class="state {{ $tenant->needsAttention() ? 'problem' : '' }}">{{ $tenant->statusText() }}</span>
                        <span class="ago">{{ Formatter::ago($tenant->lastRunAt(), $overview->now) }}</span>
                    </a>
                @endforeach
                <div class="empty" data-empty @if($tenantCount > 0) hidden @endif>{{ $tenantCount > 0 ? 'No tenants match this search.' : 'There are no tenants yet.' }}</div>
                <div class="legend">
                    <span><span class="dotsq q-ok"></span>OK</span>
                    <span><span class="dotsq q-warn"></span>Warning</span>
                    <span><span class="dotsq q-fail"></span>Failed or crashed</span>
                    <span><span class="dotsq q-idle"></span>Stale, not run yet or not applicable</span>
                    <span>One square per check, in registration order</span>
                </div>
            </div>
        </div>
    @endif
@endsection
