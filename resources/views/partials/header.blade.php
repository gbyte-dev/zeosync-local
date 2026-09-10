<header class="header">
    <div class="wrap nav">
        <a class="brand" href="{{ route('crm.entry') }}" aria-label="{{ getAppName() }} home">
            @if(function_exists('getLogo'))
                <img src="{{ getLogo() }}" alt="{{ getAppName() }}" style="height:36px;object-fit:contain;display:inline-block;vertical-align:middle;margin-right:10px">
            @else
                <span class="mark">z</span>{{ getAppName() }}<span class="brand-dot">.</span>
            @endif
        </a>

        <button class="menu" aria-expanded="false" aria-controls="navlinks">Menu</button>

        <nav id="navlinks" aria-label="Main navigation">
            <a href="{{ route('crm.entry') }}" class="{{ request()->is('/') ? 'active' : '' }}">Home</a>
            <a href="{{ route('about') }}" class="{{ request()->is('about') ? 'active' : '' }}">About</a>
            <a href="{{ route('pricing') }}" class="{{ request()->is('pricing') ? 'active' : '' }}">Pricing</a>
            <a href="{{ route('contact') }}" class="{{ request()->is('contact') ? 'active' : '' }}">Contact</a>
            <a class="navcta" href="{{ route('contact') }}">Let’s talk <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
