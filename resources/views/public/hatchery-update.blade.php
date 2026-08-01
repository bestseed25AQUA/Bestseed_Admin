<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $update->hatchery?->hatchery_name ?? 'Bestseed' }} - Update</title>

    <!-- Open Graph for social sharing -->
    <meta property="og:title" content="{{ $update->hatchery?->hatchery_name ?? 'Bestseed' }} - Update">
    <meta property="og:description" content="{{ Str::limit(strip_tags($update->caption ?? $update->title ?? ''), 150) }}">
    @if($logo)
        <meta property="og:image" content="{{ $logo }}">
    @elseif(count($mediaFiles) > 0 && !$mediaFiles[0]['is_video'])
        <meta property="og:image" content="{{ $mediaFiles[0]['url'] }}">
    @endif
    <meta property="og:type" content="article">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #0077C8 0%, #005a9e 100%);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .header-logo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .header-logo-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 700;
        }
        .header-text { color: white; }
        .header-text h1 { font-size: 16px; font-weight: 600; }
        .header-text p { font-size: 12px; opacity: 0.8; }

        /* Container */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 16px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        /* Hatchery info */
        .hatchery-info {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .hatchery-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            background: #e8f4fd;
        }
        .hatchery-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0077C8, #00a8ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: 700;
        }
        .hatchery-meta h2 { font-size: 16px; font-weight: 600; }
        .hatchery-meta .subtitle {
            font-size: 13px;
            color: #8b8b8b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .hatchery-meta .dot {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #ccc;
            display: inline-block;
        }

        /* Caption */
        .caption {
            padding: 16px 20px;
            font-size: 15px;
            line-height: 1.6;
            color: #333;
        }

        /* Hashtags */
        .hashtags {
            padding: 0 20px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .hashtag {
            background: #e8f4fd;
            color: #0077C8;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Media */
        .media-section { position: relative; }
        .media-carousel {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .media-carousel::-webkit-scrollbar { display: none; }
        .media-item {
            min-width: 100%;
            scroll-snap-align: start;
            aspect-ratio: 4/3;
            object-fit: cover;
            background: #f5f5f5;
        }
        .media-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-dots {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 6px;
        }
        .media-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transition: background 0.3s;
        }
        .media-dot.active { background: white; }
        .media-counter {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Actions */
        .actions {
            padding: 12px 20px 16px;
            display: flex;
            gap: 10px;
        }
        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .action-btn:active { transform: scale(0.97); }
        .btn-call {
            background: #0077C8;
            color: white;
        }
        .btn-whatsapp {
            background: white;
            color: #25D366;
            border: 1.5px solid #25D366;
        }
        .action-btn svg { width: 18px; height: 18px; }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px 16px 32px;
        }
        .footer p {
            font-size: 13px;
            color: #999;
        }
        .footer a {
            color: #0077C8;
            text-decoration: none;
            font-weight: 600;
        }

        /* Download banner */
        .download-banner {
            background: linear-gradient(135deg, #0077C8 0%, #005a9e 100%);
            margin: 16px;
            border-radius: 14px;
            padding: 20px;
            color: white;
            text-align: center;
        }
        .download-banner h3 { font-size: 16px; margin-bottom: 6px; }
        .download-banner p { font-size: 13px; opacity: 0.85; margin-bottom: 14px; }
        .download-btn {
            display: inline-block;
            background: white;
            color: #0077C8;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="header-logo" onerror="this.style.display='none'">
        @else
            <div class="header-logo-placeholder">B</div>
        @endif
        <div class="header-text">
            <h1>Bestseed</h1>
            <p>Hatchery Update</p>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <!-- Hatchery info -->
            <div class="hatchery-info">
                @if($logo)
                    <img src="{{ $logo }}" alt="" class="hatchery-avatar" onerror="this.outerHTML='<div class=\'hatchery-avatar-placeholder\'>{{ substr($update->hatchery?->hatchery_name ?? 'B', 0, 1) }}</div>'">
                @else
                    <div class="hatchery-avatar-placeholder">{{ substr($update->hatchery?->hatchery_name ?? 'B', 0, 1) }}</div>
                @endif
                <div class="hatchery-meta">
                    <h2>{{ $update->hatchery?->hatchery_name ?? 'Bestseed Hatchery' }}</h2>
                    <div class="subtitle">
                        @if($update->title)
                            <span>{{ $update->title }}</span>
                            <span class="dot"></span>
                        @endif
                        <span>{{ $update->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <!-- Caption -->
            @if($update->caption)
                <div class="caption">{!! $update->caption !!}</div>
            @endif

            <!-- Hashtags -->
            @if(count($hashtags) > 0)
                <div class="hashtags">
                    @foreach($hashtags as $tag)
                        <span class="hashtag">#{{ ltrim($tag, '#') }}</span>
                    @endforeach
                </div>
            @endif

            <!-- Media -->
            @if(count($mediaFiles) > 0)
                <div class="media-section">
                    <div class="media-carousel" id="mediaCarousel">
                        @foreach($mediaFiles as $media)
                            @if($media['is_video'])
                                <div class="media-item">
                                    <video controls playsinline preload="metadata"
                                        poster="" style="width:100%;height:100%;object-fit:cover;">
                                        <source src="{{ $media['url'] }}" type="video/mp4">
                                    </video>
                                </div>
                            @else
                                <img src="{{ $media['url'] }}" alt="Hatchery update" class="media-item"
                                    onerror="this.style.background='#e0e0e0'">
                            @endif
                        @endforeach
                    </div>

                    @if(count($mediaFiles) > 1)
                        <div class="media-counter" id="mediaCounter">1 / {{ count($mediaFiles) }}</div>
                        <div class="media-dots" id="mediaDots">
                            @foreach($mediaFiles as $i => $media)
                                <div class="media-dot {{ $i === 0 ? 'active' : '' }}"></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- Action buttons -->
            @php
                $mobile = $update->vendor?->mobile ? preg_replace('/\D/', '', $update->vendor->mobile) : null;
                $callUrl = $update->hatchery?->call_number ? 'tel:+91' . $update->hatchery->call_number : ($mobile ? "tel:+$mobile" : null);
                $whatsappUrl = $update->hatchery?->whatsapp_number ? 'https://wa.me/' . $update->hatchery->whatsapp_number : ($mobile ? "https://wa.me/$mobile" : null);
            @endphp

            @if($callUrl || $whatsappUrl)
                <div class="actions">
                    @if($callUrl)
                        <a href="{{ $callUrl }}" class="action-btn btn-call">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                            Call Now
                        </a>
                    @endif
                    @if($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" class="action-btn btn-whatsapp" target="_blank">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.638l4.584-1.208A11.955 11.955 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.24 0-4.318-.724-6.01-1.955l-.42-.312-2.717.716.73-2.66-.342-.544A9.956 9.956 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                            WhatsApp
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <!-- Download app banner -->
        <div class="download-banner">
            <h3>Get the Bestseed App</h3>
            <p>Browse hatcheries, book seeds, and track your deliveries</p>
            <a href="#" class="download-btn">Download App</a>
        </div>

        <div class="footer">
            <p>Shared from <a href="#">Bestseed</a></p>
        </div>
    </div>

    @if(count($mediaFiles) > 1)
    <script>
        const carousel = document.getElementById('mediaCarousel');
        const counter = document.getElementById('mediaCounter');
        const dots = document.querySelectorAll('.media-dot');
        const total = {{ count($mediaFiles) }};

        carousel.addEventListener('scroll', () => {
            const index = Math.round(carousel.scrollLeft / carousel.offsetWidth);
            counter.textContent = (index + 1) + ' / ' + total;
            dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
        });
    </script>
    @endif

</body>
</html>
