<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" {{ $attributes }}>
    {{-- Circular rotation arrows (Inovindo blue & green) --}}
    <path 
        d="M20 4 C11 4 4 11 4 20" 
        stroke="#2563eb" 
        stroke-width="2.5" 
        fill="none" 
        stroke-linecap="round"
    />
    <path 
        d="M36 20 C36 29 29 36 20 36" 
        stroke="#10b981" 
        stroke-width="2.5" 
        fill="none" 
        stroke-linecap="round"
    />
    
    {{-- Arrow heads untuk rotation --}}
    <path 
        d="M4 20 L7 17 L7 23 Z" 
        fill="#2563eb"
    />
    <path 
        d="M20 36 L17 33 L23 33 Z" 
        fill="#10b981"
    />
    
    {{-- Calendar grid (center) --}}
    <rect 
        x="12" 
        y="12" 
        width="16" 
        height="16" 
        rx="2" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="1.5"
    />
    
    {{-- Calendar header line --}}
    <line 
        x1="12" 
        y1="16" 
        x2="28" 
        y2="16" 
        stroke="currentColor" 
        stroke-width="1.5"
    />
    
    {{-- Calendar dots (scheduled days) --}}
    <circle cx="16" cy="20" r="1.2" fill="#2563eb"/>
    <circle cx="20" cy="20" r="1.2" fill="#10b981"/>
    <circle cx="24" cy="20" r="1.2" fill="#2563eb"/>
    <circle cx="16" cy="24" r="1.2" fill="#10b981"/>
    <circle cx="24" cy="24" r="1.2" fill="#2563eb"/>
</svg>
