<h2>{{ __('app.apps.config') }} ({{ __('app.optional') }}) @include('items.enable')</h2>
<div class="items">
    <div class="input">
        <label>{{ strtoupper(__('app.url')) }}</label>
        {!! Form::text('config[override_url]', isset($item) ? $item->getconfig()->override_url : null, ['placeholder' => __('app.apps.override'), 'id' => 'override_url', 'class' => 'form-control']) !!}
    </div>
    <div class="input">
        <label>Email address</label>
        {!! Form::text('config[email]', isset($item) ? $item->getconfig()->email : null, ['placeholder' => __('Email address'), 'data-config' => 'email', 'class' => 'form-control config-item']) !!}
    </div>
    <div class="input">
        <label>{{ __('app.apps.password') }}</label>
        {!! Form::input('password', 'config[password]', '', ['placeholder' => __('app.apps.password'), 'data-config' => 'password', 'class' => 'form-control config-item']) !!}
    </div>
    <div class="input">
        <label>Stats to show (choose up to 3, defaults to Total + Playing if none selected)</label>
        @php
            $selectedstats = (isset($item) ? $item->getConfig()->availablestats ?? null : null) ?: ['total', 'playing'];
        @endphp
        <div class="bootleg-stats-select" style="display: flex; flex-direction: column; align-items: flex-start; width: 100%; gap: 4px; margin: 4px 0 20px;">
            @foreach (App\SupportedApps\BootLeg\BootLeg::getAvailableStats() as $key => $label)
                <label class="bootleg-stat-option" style="display: flex; flex-direction: row; align-items: center; justify-content: flex-start; width: auto; gap: 6px; margin: 0; cursor: pointer; user-select: none;">
                    <input type="checkbox" class="bootleg-stat-checkbox" name="config[availablestats][]" value="{{ $key }}" style="order: -1; flex: none; width: auto; margin: 0; accent-color: #2098d1; cursor: pointer;" @if (in_array($key, $selectedstats)) checked @endif />
                    <span style="order: 2;">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="input">
        <button style="margin-top: 32px;" class="btn test" id="test_config">Test</button>
    </div>
</div>
<style>
    .bootleg-stat-option.bootleg-stat-option--disabled {
        opacity: 0.5;
    }
    .bootleg-stat-option.bootleg-stat-option--disabled input[type="checkbox"] {
        cursor: not-allowed;
    }
</style>
<script>
    (function () {
        var boxes = document.querySelectorAll('.bootleg-stat-checkbox');

        function sync() {
            var checkedCount = 0;
            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i].checked) checkedCount++;
            }
            for (var i = 0; i < boxes.length; i++) {
                var atLimit = !boxes[i].checked && checkedCount >= 3;
                boxes[i].disabled = atLimit;
                boxes[i].closest('.bootleg-stat-option').classList.toggle('bootleg-stat-option--disabled', atLimit);
            }
        }

        for (var i = 0; i < boxes.length; i++) {
            boxes[i].addEventListener('change', sync);
        }
        sync();
    })();
</script>
