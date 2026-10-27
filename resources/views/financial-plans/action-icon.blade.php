<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@if($icon === 'edit')<path d="m16 3 5 5-12 12-6 1 1-6Z M14 5l5 5"/>
@elseif($icon === 'trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>
@else<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>@if($icon === 'eye-off')<path d="m3 3 18 18"/>@endif
@endif
</svg>
