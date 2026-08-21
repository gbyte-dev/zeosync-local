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
            $selectedValues = array_map(
                fn ($value) => (string) $value,
                $saved
            );
        } elseif (is_string($saved) && trim($saved) !== '') {
            $selectedValues = array_values(
                array_filter(
                    array_map(
                        'trim',
                        preg_split(
                            '/\s*,\s*|\s*;\s*|\r\n|\n/',
                            $saved
                        )
                    ),
                    fn ($value) => $value !== ''
                )
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
            $selectedValues = array_map(
                fn ($value) => (string) $value,
                $saved
            );
        } elseif (is_string($saved) && trim($saved) !== '') {
            $selectedValues = array_values(
                array_filter(
                    array_map(
                        'trim',
                        preg_split(
                            '/\s*,\s*|\s*;\s*|\r\n|\n/',
                            $saved
                        )
                    ),
                    fn ($value) => $value !== ''
                )
            );
        }
    }
@endphp

<div class="mb-2">

    <select
        name="attributes[{{ $field['name'] }}][]"
        {{ $idreq }}
        multiple
        class="form-select amazon-multiselect"
        style="width: 100%;"
        data-placeholder="Select {{ $field['title'] ?? $field['name'] }}"
    >
        @foreach($field['options'] as $option)
            <option
                value="{{ $option['value'] }}"
                {{ in_array((string) $option['value'], $selectedValues, true) ? 'selected' : '' }}
            >
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>

    @if(!empty($field['description']) || $fieldHint)
        <div
            class="form-text mt-1 d-flex justify-content-between align-items-start"
            style="font-size:11px;"
        >
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

@once

    {{-- Select2 CSS --}}
    <link
        href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
        rel="stylesheet"
    />

    <style>
        .amazon-multiselect + .select2 {
            width: 100% !important;
        }

        .amazon-multiselect + .select2 .select2-selection--multiple {
            min-height: 38px;
            border: 1px solid #aaaaeb;
            border-radius: 4px;
            padding: 2px 5px;
            font-size: small;
        }

        .amazon-multiselect + .select2 .select2-selection__rendered {
            padding: 0;
        }

        .amazon-multiselect + .select2 .select2-search__field {
            margin-top: 5px;
            font-size: small;
        }

        .amazon-multiselect + .select2 .select2-selection__choice {
            margin-top: 4px;
        }

        .amazon-multiselect.is-invalid + .select2
        .select2-selection--multiple {
            border: 3px solid #dc3545 !important;
            background: #fff0f0 !important;
        }
    </style>

    {{-- Select2 JS --}}
    <script
        src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js">
    </script>

    <script>
        $(document).ready(function () {

            $('.amazon-multiselect').each(function () {

                const $select = $(this);

                if ($select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                $select.select2({
                    width: '100%',
                    placeholder: $select.data('placeholder') || 'Select options',
                    closeOnSelect: false,
                    allowClear: true
                });

            });

        });
    </script>

@endonce