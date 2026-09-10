<footer id="siteFooter">
    <div class="wrap footergrid">
        <div>
            <a href="{{ route('crm.entry') }}" style="text-decoration:none;">
                <h5 class="footer-brand">{{ getAppName() }}</h5>
            </a>
            <p>Amazon. Shopify.<br>A better way to work together.</p>
        </div>
        <div>
            <h3>Explore</h3>
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('pricing') }}">Pricing</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div>
            <h3>Legal</h3>
            <a href="{{ route('terms') }}">Terms</a>
            <a href="{{ route('privacy') }}">Privacy</a>
        </div>
        <div class="footnote">
            <p>Built around the work of selling.</p>
            <p>Independent service. Not affiliated with or endorsed by Amazon or Shopify.</p>
        </div>
    </div>
    <div class="wrap footerbottom"><span>&copy; {{ date('Y') }} {{ getAppName() }}</span><span>Keep commerce moving.</span></div>
</footer>
