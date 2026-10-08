@auth
    <div class="user-storage-usage" style="font-size:11px;line-height:1.5;color:rgba(255,255,255,.75);margin-top:4px" title="Miejsce zajęte na serwerze przez aktualnie zapisane pliki przypisane do Twojego konta">
        <i class="ti ti-database" aria-hidden="true"></i>
        Twoje pliki: {{ \App\Models\Document::formatBytes(app(\App\Services\DocumentQuotaService::class)->used(auth()->id())) }}
    </div>
@endauth
