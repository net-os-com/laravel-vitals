<dialog class="dialog" id="vitals-mute" aria-labelledby="vitals-mute-title">
    <form method="dialog" action="{{ route('vitals.mutes.store') }}" data-mute-form>
        <h3 id="vitals-mute-title">Mute <span data-mute-title></span></h3>
        <p class="hint">A muted problem stays visible here, but it no longer counts in the totals or in status.json.</p>
        <input type="hidden" name="scope">
        <input type="hidden" name="check">
        <input type="hidden" name="tenant">
        <label class="check" data-mute-all-row><input type="checkbox" name="all_tenants" value="1"> Mute this check for all tenants</label>
        <label class="field"><span>For how long</span>
            <select name="duration">
                @foreach(\NetOs\Vitals\Muting\MuteDuration::cases() as $duration)
                    <option value="{{ $duration->value }}" @selected($duration === \NetOs\Vitals\Muting\MuteDuration::UntilRecovered)>{{ $duration->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="field"><span>Reason <span class="optional">(optional)</span></span>
            <input type="text" name="reason" maxlength="255" placeholder="For example: Auth0 migration in progress">
        </label>
        <p class="error" data-mute-error role="alert"></p>
        <div class="actions end">
            <button class="btn" type="button" data-mute-cancel>Cancel</button>
            <button class="btn primary" type="submit">Mute</button>
        </div>
    </form>
</dialog>
