<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Temporarily unavailable') }} | {{ config('app.name', 'Aguhon') }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f7fb;
            color: #172b4d;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(245, 196, 81, .15), transparent 42%),
                #f5f7fb;
        }

        main {
            width: min(100%, 560px);
            padding: clamp(32px, 7vw, 64px);
            border: 1px solid #e4e9f1;
            border-radius: 24px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 24px 70px rgba(23, 43, 77, .09);
            text-align: center;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #172b4d;
            font-size: .9rem;
            font-weight: 800;
            letter-spacing: .14em;
        }

        .brand-mark {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 12px;
            background: #172b4d;
            color: #f5c451;
        }

        .illustration {
            display: grid;
            width: 112px;
            height: 112px;
            place-items: center;
            margin: 44px auto 28px;
            border: 1px solid #f1e4be;
            border-radius: 32px;
            background: #fff9e9;
            color: #a87506;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #fff5d8;
            color: #805800;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .status::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #d69a16;
            content: "";
        }

        h1 {
            margin: 18px 0 12px;
            color: #172b4d;
            font-size: clamp(1.8rem, 6vw, 2.5rem);
            line-height: 1.15;
            letter-spacing: -.04em;
        }

        .message {
            margin: 0 auto;
            max-width: 390px;
            color: #607087;
            font-size: 1rem;
            line-height: 1.75;
        }

        .footnote {
            margin: 36px 0 0;
            padding-top: 20px;
            border-top: 1px solid #edf0f5;
            color: #8793a5;
            font-size: .8rem;
        }

        @media (prefers-reduced-motion: no-preference) {
            .status::before { animation: pulse 1.8s ease-in-out infinite; }
            @keyframes pulse {
                50% { opacity: .4; }
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="brand" aria-label="{{ config('app.name', 'Aguhon') }}">
            <span class="brand-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                    <path d="M12 3 4.5 20h3.8l1.5-3.6h4.4l1.5 3.6h3.8L12 3Zm0 6 1.1 3.4h-2.2L12 9Z" fill="currentColor"/>
                </svg>
            </span>
            <span>{{ config('app.name', 'Aguhon') }}</span>
        </div>

        <div class="illustration" aria-hidden="true">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                <path d="M17 43h30l-3.2-5.3V27a11.8 11.8 0 0 0-23.6 0v10.7L17 43Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
                <path d="M27 48a5.2 5.2 0 0 0 10 0M12 22l-3-2m46 2 3-2M32 10V6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>

        <span class="status">{{ __('Maintenance in progress') }}</span>
        <h1>{{ __('We’ll be back soon.') }}</h1>
        <p class="message">
            {{ __('We’re making a few improvements to Aguhon right now. Thanks for your patience. Please check back in a little while.') }}
        </p>
        <p class="footnote">{{ __('Your progress is safe. We’ll be ready for you soon.') }}</p>
    </main>
</body>
</html>
