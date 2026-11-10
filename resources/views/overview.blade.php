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
                @php
                    $backlog = $overview->queueBacklog;
                    $backlogText = match (true) {
                        $backlog['size'] === null => null,
                        $backlog['size'] === 0 => "No check jobs are waiting on the {$backlog['queue']} queue.",
                        $backlog['oldest_seconds'] !== null => "{$backlog['size']} check ".str('job')->plural($backlog['size'])." waiting on the {$backlog['queue']} queue, the oldest for ".Formatter::duration($backlog['oldest_seconds']).'.',
                        default => "{$backlog['size']} check ".str('job')->plural($backlog['size'])." waiting on the {$backlog['queue']} queue.",
                    };
                @endphp
                {{ $backlogText }}
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

    @if($overview->queueBehind)
        <div class="alert" role="status"><span class="dotsq q-fail"></span><span><strong>Checks are falling behind.</strong>
            The oldest job on the {{ $overview->queueBacklog['queue'] }} queue has waited {{ Formatter::duration((int) $overview->queueBacklog['oldest_seconds']) }}. Check that a worker processes this queue.</span></div>
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

    @if($overview->changes->isNotEmpty())
        <div class="sect">
            <div class="secthead"><h3>Recent changes</h3><span>Newest first</span></div>
            <div class="card list">
                @foreach($overview->changes as $entry)
                    <a class="crow" href="{{ $entry->url }}">
                        <span class="dotsq q-{{ \NetOs\Vitals\Dashboard\Health::fromStatus($entry->change->to_status)->tone() }}"></span>
                        <span><b>{{ $entry->checkLabel }}@if($entry->tenantName) · {{ $entry->tenantName }}@endif</b><small>{{ $entry->change->from_status->label() }} → {{ $entry->change->to_status->label() }}@if($entry->change->summary !== '') · {{ $entry->change->summary }}@endif</small></span>
                        <span class="ago">{{ Formatter::dateTime($entry->change->changed_at) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if($overview->mutes->isNotEmpty())
        <div class="sect">
            <div class="secthead"><h3>Muted</h3><span>Not counted in the totals or in status.json</span></div>
            <div class="card list">
                @foreach($overview->mutes as $mute)
                    <div class="mrow">
                        <span><b>{{ $mute->targetLabel() }}</b><small>{{ $mute->description() }}</small></span>
                        <span class="ago">since {{ Formatter::dateTime($mute->created_at) }}</span>
                        <button class="linkbtn" type="button" data-unmute="{{ route('vitals.mutes.destroy', $mute->id) }}">Unmute</button>
                    </div>
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
                @foreach($overview->central->sortByDesc(fn ($check) => [$check->attentionHealth()->severity(), $check->health->severity()]) as $check)
                    <a class="card tile" href="{{ route('vitals.check', $check->check->key()) }}" data-last-run="{{ $check->lastRunAt()?->getTimestamp() }}">
                        <div class="tile-top"><b>{{ $check->check->label() }}</b>@include('vitals::partials.status-badges', ['check' => $check])</div>
                        <p>{{ $check->summary() }}</p>
                        <div class="tile-foot">@include('vitals::partials.runs', ['snapshot' => $check])<span class="ago">{{ Formatter::ago($check->lastRunAt(), $overview->now) }}</span></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if($overview->usesTenancy && $overview->tenantChecks()->isNotEmpty())
        <div class="sect">
            <div class="secthead"><h3>Tenant checks</h3><span>Each check over all tenants</span></div>
            <div class="card list">
                @foreach($overview->tenantChecks() as $matrix)
                    <a class="crow" href="{{ route('vitals.check.tenants', $matrix->check->key()) }}">
                        <span class="dotsq q-{{ $matrix->worst()->tone() }}"></span>
                        <span><b>{{ $matrix->check->label() }}</b><small>{{ $matrix->check->frequency->label() }} · {{ $matrix->applicable() }} {{ str('tenant')->plural($matrix->applicable()) }}</small></span>
                        <span class="counts">
                            @if($matrix->failing() > 0)<span class="badge b-fail">{{ $matrix->failing() }} failing</span>@endif
                            @if($matrix->count(Health::Warning) > 0)<span class="badge b-warn">{{ $matrix->count(Health::Warning) }} {{ str('warning')->plural($matrix->count(Health::Warning)) }}</span>@endif
                            @if($matrix->count(Health::Stale) > 0)<span class="badge b-idle">{{ $matrix->count(Health::Stale) }} stale</span>@endif
                            @if($matrix->muted() > 0)<span class="badge b-mute">{{ $matrix->muted() }} muted</span>@endif
                            @if($matrix->worst()->severity() < 2 && $matrix->muted() === 0)<span class="ago">All OK</span>@endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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
                <div class="empty" data-empty @if($tenantCount > 0) hidden @endif>{{ $tenantCount > 0 ? 'No tenants match this search or filter.' : 'There are no tenants yet.' }}</div>
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
