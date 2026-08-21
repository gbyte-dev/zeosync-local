@php
    $selectedValues = [];

    if (isset($prodAttri)) {
        $saved = optional($prodAttri->firstWhere('attribute_name', $field['name']))->attribute_value;

        if (is_string($saved)) {
            $decoded = json_decode($saved, true);
            $saved = is_array($decoded) ? $decoded : $saved;
        }

        if (is_array($saved)) {
            $selectedValues = array_map(fn ($value) => (string) $value, $saved);
        } elseif (is_string($saved) && trim($saved) !== '') {
            $selectedValues = array_values(array_filter(
                array_map('trim', preg_split('/\s*,\s*|\s*;\s*|\r\n|\n/', $saved)),
                fn ($value) => $value !== ''
            ));
        }
    }

    if (isset($prodAttrijson) && isset($prodAttrijson[$field['name']])) {
        $saved = $prodAttrijson[$field['name']];

        if (is_string($saved)) {
            $decoded = json_decode($saved, true);
            $saved = is_array($decoded) ? $decoded : $saved;
        }

        if (is_array($saved)) {
            $selectedValues = array_map(fn ($value) => (string) $value, $saved);
        } elseif (is_string($saved) && trim($saved) !== '') {
            $selectedValues = array_values(array_filter(
                array_map('trim', preg_split('/\s*,\s*|\s*;\s*|\r\n|\n/', $saved)),
                fn ($value) => $value !== ''
            ));
        }
    }
@endphp

<div class="mb-2">
    <select
        name="attributes[{{ $field['name'] }}][]"
        {{ $idreq }}
        multiple
        size="{{ min(6, max(3, count($field['options'] ?? []))) }}"
        class="form-select form-select-sm"
        style="font-size: small;
        @if(!empty($php_errormsg))
            border:3px solid #dc3545 !important;
            background:#fff0f0 !important;
        @else
        border:1px solid #aaaaeb !important;
        @endif
        ">

        @foreach($field['options'] as $option)
            <option
                value="{{ $option['value'] }}"
                {{ in_array((string) $option['value'], $selectedValues, true) ? 'selected' : '' }}>
                {{ $option['label'] }}
            </option>
        @endforeach

    </select>

    @if(!empty($field['description']) || $fieldHint)
        <div class="form-text mt-1 d-flex justify-content-between align-items-start"
             style="font-size:11px;">

            <div class="text-dark pe-2">
                {{ $field['description'] ?? '' }}
            </div>

            @if($fieldHint)
                <div class="text-primary text-end flex-shrink-0 ms-2">
                    <strong>{{ $fieldHint['title'] }}:</strong>
                    {{ $fieldHint['example'] }}
                </div>
            @endif

        </div>
    @endif
</div>
