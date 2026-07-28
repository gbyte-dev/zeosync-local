@extends('layouts.app')



@section('content')



<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0">Subscription</h2>

  

    </div>






</div>



@endsection



@push('styles')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@endpush



@push('scripts')
<script src="https://unpkg.com/@shopify/app-bridge@3"></script>
<script src="https://unpkg.com/@shopify/app-bridge-utils@3"></script>
<script>

  const AppBridge = window['app-bridge'];
  const AppBridgeUtils = window['app-bridge-utils']; // This is what was likely undefined
  const createApp = AppBridge.default;
  let host = new URLSearchParams(window.location.search).get("host");
  const app = createApp({
    apiKey: "e90411fc01ef6455376cbf23a7cffe79",
    host: host,
  });


async function getAllSessionData() {
  try {
    // 1. Get the raw token from Shopify App Bridge
    const token = await window['app-bridge-utils'].getSessionToken(app);

    // 2. JWTs are "Header.Payload.Signature" - we want the middle part
    const base64Payload = token.split('.')[1];
    
    // 3. Decode Base64 to a JSON string, then parse it to an Object
    const sessionData = JSON.parse(atob(base64Payload));

    // 4. View everything in your console
    console.log("--- ALL SESSION DATA ---");
    console.table(sessionData); 

    return sessionData;
  } catch (error) {
    console.error("Could not get session. Are you inside a Shopify Admin?", error);
  }
}

// Call it
getAllSessionData();

// geturl();
</script>
@endpush