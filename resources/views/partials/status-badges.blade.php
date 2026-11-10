<span class="badges">
    @include('vitals::partials.badge', ['tone' => $check->health->tone(), 'label' => $check->health->label()])
    @if($check->isFlapping())
        <span class="badge b-flap" title="{{ $check->flappingDescription() }}">Flapping</span>
    @endif
    @if($check->isMuted())
        <span class="badge b-mute" title="{{ $check->mute->description() }}">Muted</span>
    @endif
</span>
