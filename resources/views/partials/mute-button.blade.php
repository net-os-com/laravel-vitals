@php
    /** @var \NetOs\Vitals\Dashboard\CheckSnapshot $check */
    $tenantKey = $check->tenant?->key;
@endphp
@if($check->mute && $check->mute->targets($check->check, $tenantKey))
    <button class="{{ $class }}" type="button" data-unmute="{{ route('vitals.mutes.destroy', $check->mute->id) }}" title="{{ $check->mute->description() }}">Unmute</button>
@elseif(! $check->mute)
    <button class="{{ $class }}" type="button" data-mute="{{ json_encode([
        'scope' => $check->check->scope->value,
        'check' => $check->check->key(),
        'tenant' => $tenantKey,
        'title' => $check->check->label().($check->tenant ? ' for '.$check->tenant->name : ''),
        'allTenants' => $check->tenant !== null,
    ]) }}">Mute</button>
@endif
