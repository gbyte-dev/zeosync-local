@php
$selectedValues = [];

if (isset($prodAttri)) {
$saved = optional(
$prodAttri->firstWhere('attribute_name', $field['name'])
)->attribute_value;

if (is_string($saved)) {
$decoded = json_decode($saved, true);
$saved = is_array($decoded) ? $decoded : $saved;
}

if (is_array($saved)) {
$selectedValues = array_map('strval', $saved);
} elseif (is_string($saved) && trim($saved) !== '') {
$selectedValues = array_filter(
array_map('trim', preg_split('/,|;|\r\n|\n/', $saved))
);
}
}

if (isset($prodAttrijson) && isset($prodAttrijson[$field['name']])) {
$saved = $prodAttrijson[$field['name']];

if (is_string($saved)) {
$decoded = json_decode($saved, true);
$saved = is_array($decoded) ? $decoded : $saved;
}

if (is_array($saved)) {
$selectedValues = array_map('strval', $saved);
}
}

$dropdownId = 'dropdown_' . md5($field['name'] . uniqid());
@endphp

<div class="mb-2">

    <div class="dropdown w-100">

        <button
            type="button"
            class="form-select form-select-sm text-start"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            style="
                font-size: small;
                border: 1px solid #aaaaeb !important;
                background: #fff;
            ">
            Select {{ $field['title'] ?? $field['name'] }}
        </button>

        <div
            class="dropdown-menu w-100 p-2 shadow-sm"
            style="max-height: 250px; overflow-y: auto;">

            @foreach($field['options'] as $option)

            <label
                class="dropdown-item px-2 py-1"
                style="cursor:pointer; font-size:small;">

                <input
                    type="checkbox"
                    name="attributes[{{ $field['name'] }}][]"
                    value="{{ $option['value'] }}"
                    class="form-check-input me-2"
                    {{ in_array((string) $option['value'], $selectedValues, true) ? 'checked' : '' }}>

                {{ $option['label'] }}

            </label>

            @endforeach

        </div>

    </div>

    @if(!empty($field['description']) || $fieldHint)

    <div
        class="form-text mt-1 d-flex justify-content-between align-items-start"
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