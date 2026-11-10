@php
    use NetOs\Vitals\Dashboard\CheckDetail;
    use NetOs\Vitals\Dashboard\Health;
    use NetOs\Vitals\Support\Formatter;
    /** @var CheckDetail $detail */
    $snapshot = $detail->snapshot;
    $check = $snapshot->check;
    $tenant = $snapshot->tenant;
    $state = $snapshot->state;
    $tenantQuery = $tenant ? ['tenant' => $tenant->key] : [];
    $okPercentage = $detail->okPercentage();
    $sinceSuccess = $detail->secondsSinceLastSuccess();
    $tones = fn ($status) => \NetOs\Vitals\Dashboard\Health::fromStatus($status)->tone();
@endphp
@extends('vitals::layout')

@section('title', $check->label())

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="{{ route('vitals.overview') }}">Overview</a><span>/</span>
        @if($tenant)<a href="{{ route('vitals.tenant', $tenant->key) }}">{{ $tenant->name }}</a><span>/</span>@endif
        <span>{{ $check->label() }}</span>
    </nav>
    <div class="pagehead" style="margin-top:10px">
        <div>
            <h2 class="page">{{ $check->label() }}</h2>
            <div class="facts">
                <span class="badge b-plain">{{ $check->key() }}</span>
                <span class="badge b-plain">{{ $check->scope->label() }}</span>
                <span class="badge b-plain">{{ $check->frequency->label() }}</span>
                <span class="badge b-plain">Timeout {{ $check->check->timeoutSeconds() }}s</span>
                @if($tenant)<a class="badge b-link" href="{{ route('vitals.check.tenants', $check->key()) }}">All tenants →</a>@endif
                @if($check->check->runbookUrl())<a class="badge b-link" href="{{ $check->check->runbookUrl() }}" target="_blank" rel="noopener">Runbook ↗</a>@endif
            </div>
        </div>
        <div class="actions">
            <div class="chips" role="group" aria-label="Range">
                @foreach(CheckDetail::RANGES as $range => $label)
                    <a class="chip {{ $detail->range === $range ? 'on' : '' }}" href="{{ route('vitals.check', ['check' => $check->key(), ...$tenantQuery, 'range' => $range]) }}">{{ $label }}</a>
                @endforeach
            </div>
            @if($snapshot->health !== Health::NotApplicable)
                @include('vitals::partials.mute-button', ['check' => $snapshot, 'class' => 'btn'])
                <button class="btn primary" type="button" data-run="{{ route('vitals.check.run', ['check' => $check->key(), ...$tenantQuery]) }}">Run now</button>
            @endif
        </div>
    </div>

    <div class="detail">
        <div class="card now" data-last-run="{{ $snapshot->lastRunAt()?->getTimestamp() }}">
            <div class="now-top">
                @include('vitals::partials.status-badges', ['check' => $snapshot])
                <div>
                    <h4>{{ $snapshot->summary() !== '' ? $snapshot->summary() : $snapshot->health->label() }}</h4>
                    @if($state)
                        <div class="meta">
                            @if($snapshot->health === Health::Stale)
                                No result since {{ Formatter::dateTime($state->last_run_at) }} · expected {{ lcfirst($check->frequency->label()) }}
                            @else
                                Since {{ Formatter::dateTime($state->status_since) }}@if($state->consecutive_failures > 0) · {{ $state->consecutive_failures }} consecutive {{ str('failure')->plural($state->consecutive_failures) }}@endif
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <div class="figs">
                <div><b>{{ $okPercentage === null ? '—' : rtrim(rtrim(number_format($okPercentage, 1), '0'), '.').'%' }}</b><span>Runs OK in {{ CheckDetail::RANGES[$detail->range] }}</span></div>
                <div><b>{{ $sinceSuccess === null ? 'Never' : Formatter::duration($sinceSuccess) }}</b><span>Since last success</span></div>
                <div><b>{{ Formatter::milliseconds($detail->medianDuration()) }}</b><span>Median duration</span></div>
            </div>
        </div>
        <div class="card kv">
            <h4>Latest result @if($detail->latest)<span class="ago" style="font-weight:400">· {{ Formatter::dateTime($detail->latest->finished_at) }}</span>@endif</h4>
            @if($detail->latest && $detail->latest->meta)
                <dl>
                    @foreach($detail->latest->meta as $key => $value)
                        @php $sentryUrl = $key === 'sentry_event_id' && is_string($value) ? \NetOs\Vitals\Support\SentryEvents::url($value) : null; @endphp
                        <dt>{{ $key }}</dt><dd>@if($sentryUrl)<a href="{{ $sentryUrl }}" target="_blank" rel="noopener">{{ $value }} ↗</a>@else{{ is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null') }}@endif</dd>
                    @endforeach
                </dl>
            @elseif($detail->latest)
                <p>This result has no meta.</p>
            @else
                <p>No results yet.</p>
            @endif
        </div>
    </div>

    <div class="card heat">
        <div class="heat-grid" role="img" aria-label="Runs per hour since {{ Formatter::dateTime($detail->since) }}">
            <span></span>
            @for($hour = 0; $hour < 24; $hour++)<span class="hr">{{ $hour % 3 === 0 ? sprintf('%02d', $hour) : '' }}</span>@endfor
            @foreach($detail->heatmap() as $row)
                <span class="day">{{ $row['day'] }}</span>
                @foreach($row['cells'] as $cell)<i class="{{ $cell['tone'] }}" title="{{ $cell['title'] }}"></i>@endforeach
            @endforeach
        </div>
    </div>

    <div class="split">
        <div class="card">
            <div class="cardhead">
                <h4>Runs</h4>
                <span class="pager">
                    <span class="ago">{{ number_format($detail->runs->total()) }} {{ str('run')->plural($detail->runs->total()) }}@if($detail->runs->lastPage() > 1) · page {{ $detail->runs->currentPage() }} of {{ $detail->runs->lastPage() }}@endif</span>
                    @if($detail->runs->previousPageUrl())<a href="{{ $detail->runs->previousPageUrl() }}">Newer</a>@endif
                    @if($detail->runs->nextPageUrl())<a href="{{ $detail->runs->nextPageUrl() }}">Older</a>@endif
                </span>
            </div>
            <div class="tbl">
                @if($detail->runs->isEmpty())
                    <div class="empty">No runs in this period.</div>
                @else
                    <table>
                        <thead><tr><th>Finished</th><th>Status</th><th>Summary</th><th>Duration</th></tr></thead>
                        <tbody>
                            @foreach($detail->runs as $run)
                                <tr>
                                    <td class="num" style="white-space:nowrap">{{ Formatter::dateTime($run->finished_at) }}</td>
                                    <td>@include('vitals::partials.badge', ['tone' => $tones($run->status), 'label' => $run->status->label()])</td>
                                    <td>{{ $run->summary }}</td>
                                    <td class="mono" style="white-space:nowrap">{{ Formatter::exactMilliseconds($run->duration_ms) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="cardhead"><h4>Status changes</h4></div>
            @php $changes = $detail->statusChanges(); @endphp
            @if($changes === [])
                <div class="empty">No status changes in this period.</div>
            @else
                <ul class="changes">
                    @foreach($changes as $change)
                        <li><span class="dotsq q-{{ $tones($change['to']) }}"></span>{{ $change['from']->label() }} → {{ $change['to']->label() }}<small>{{ Formatter::dateTime($change['at']) }}</small></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
