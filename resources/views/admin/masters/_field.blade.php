@php
    $name = $f['name'];
    $type = $f['type'] ?? 'text';
    $val  = old($name, $value instanceof \Carbon\Carbon ? $value->toDateString() : $value);
@endphp

<div>
    <label class="label" for="{{ $name }}">
        {{ $f['label'] }}@if (! empty($f['required'])) <span class="text-rose-500">*</span>@endif
    </label>

    @if ($type === 'select')
        <select name="{{ $name }}" class="input" @if (! empty($f['required'])) required @endif>
            <option value="">— select —</option>
            @foreach (($options[$f['options']] ?? []) as $k => $v)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>

    @elseif ($type === 'textarea')
        <textarea name="{{ $name }}" rows="{{ $f['rows'] ?? 3 }}" class="input"
                  @if (! empty($f['required'])) required @endif>{{ $val }}</textarea>

    @elseif ($type === 'file')
        @if ($val)
            <img src="{{ asset('storage/' . $val) }}" class="mb-2 h-16 w-16 rounded-lg object-cover" alt="">
        @endif
        <input type="file" name="{{ $name }}" accept="image/*" class="input">

    @elseif ($type === 'password')
        <input type="password" name="{{ $name }}" class="input" placeholder="Leave blank to keep current">

    @elseif ($type === 'checkbox')
        <label class="mt-2 flex items-center gap-2 text-sm">
            <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $val)
                   class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
            Enabled
        </label>

    @else
        <input type="{{ $type }}" name="{{ $name }}" class="input"
               @if (! empty($f['step'])) step="{{ $f['step'] }}" @endif
               @if (! empty($f['required'])) required @endif
               value="{{ $val }}">
    @endif

    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
