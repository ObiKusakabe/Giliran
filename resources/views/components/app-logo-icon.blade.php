<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" {{ $attributes }}>
    {{-- Rotating arrows group (for animation) --}}
    <g class="arrows-group" style="transform-origin: 50px 50px;">
        {{-- Arc biru (atas) --}}
        <path 
            d="M 13.29 40.16 A 38 38 0 0 1 81.87 29.30" 
            fill="none" 
            stroke="#2563eb" 
            stroke-width="7" 
            stroke-linecap="round"
        />
        <polygon 
            points="89.17,40.54 71.20,29.40 86.30,19.60" 
            fill="#2563eb"
        />
        
        {{-- Arc hijau (bawah) --}}
        <path 
            d="M 86.71 59.84 A 38 38 0 0 1 18.13 70.70" 
            fill="none" 
            stroke="#16a34a" 
            stroke-width="7" 
            stroke-linecap="round"
        />
        <polygon 
            points="10.83,59.46 28.80,70.60 13.70,80.40" 
            fill="#16a34a"
        />
    </g>
    
    {{-- Calendar icon di center --}}
    <g transform="translate(50,50)">
        <rect 
            x="-18.0" 
            y="-16.2" 
            width="36.0" 
            height="32.4" 
            rx="4" 
            fill="none" 
            stroke="currentColor" 
            stroke-width="4"
        />
        <line 
            x1="-18.0" 
            y1="-7.1" 
            x2="18.0" 
            y2="-7.1" 
            stroke="currentColor" 
            stroke-width="4"
        />
        <circle cx="-9.4" cy="1.0" r="2.7" fill="#2563eb"/>
        <circle cx="0.0" cy="1.0" r="2.7" fill="#16a34a"/>
        <circle cx="9.4" cy="1.0" r="2.7" fill="#2563eb"/>
        <circle cx="-9.4" cy="9.7" r="2.7" fill="#16a34a"/>
        <circle cx="0.0" cy="9.7" r="2.7" fill="#2563eb"/>
        <circle cx="9.4" cy="9.7" r="2.7" fill="#16a34a"/>
    </g>
</svg>
