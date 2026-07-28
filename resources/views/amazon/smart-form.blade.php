<!DOCTYPE html>
<html>

<head>

    <title>Amazon Smart Form</title>

    <link href="{{ asset('assets/css/bootstrap.min.css') }}"
        rel="stylesheet">

</head>

<body>

<div class="container mt-5">

    <div class="card">

        <div class="card-header">

            Amazon Smart Form Builder

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4">

                    <label>Product Type</label>

                    <select
                        class="form-select"
                        id="product_type">

                        <option value="">Select</option>

                        <option value="SHIRT">
                            SHIRT
                        </option>

                        <option value="HEADPHONES">
                            HEADPHONES
                        </option>

                    </select>

                </div>

                <div class="col-md-2">

                    <label>&nbsp;</label>

                    <button
                        class="btn btn-primary w-100"
                        onclick="fetchForm()">

                        Fetch Form

                    </button>

                </div>

            </div>

            <hr>

            <form id="dynamicForm">

                <div id="formFields"></div>

            </form>

        </div>

    </div>

</div>

<script>

function fetchForm()
{
    let productType =
        document.getElementById(
            'product_type'
        ).value;

    fetch(
        "{{ route('amazon.smart.fetch') }}",
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json',

                'X-CSRF-TOKEN':
                    '{{ csrf_token() }}'
            },

            body: JSON.stringify({
                product_type:
                    productType
            })
        }
    )
    .then(res => res.json())
    .then(response => {

        let html = '';

        response.fields.forEach(field => {

            html += `
                <div class="mb-3">

                    <label>
                        ${field.replaceAll('_',' ')}
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="${field}">

                </div>
            `;
        });

        document
            .getElementById('formFields')
            .innerHTML = html;
    });
}

</script>

</body>
</html>