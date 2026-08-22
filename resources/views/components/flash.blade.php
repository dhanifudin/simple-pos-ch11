@if (session('status'))
    <div x-data="{ open: true }" x-show="open" x-transition
         class="mb-4 flex items-start justify-between gap-3 rounded-md border border-signal-green/30 bg-signal-green-soft px-4 py-3 text-sm text-signal-green">
        <span>{{ session('status') }}</span>
        <button type="button" @click="open = false" aria-label="Tutup notifikasi" class="text-signal-green/60 hover:text-signal-green">&times;</button>
    </div>
@endif
@if (session('error'))
    <div x-data="{ open: true }" x-show="open" x-transition
         class="mb-4 flex items-start justify-between gap-3 rounded-md border border-signal-red/30 bg-signal-red-soft px-4 py-3 text-sm text-signal-red">
        <span>{{ session('error') }}</span>
        <button type="button" @click="open = false" aria-label="Tutup notifikasi" class="text-signal-red/60 hover:text-signal-red">&times;</button>
    </div>
@endif
@if ($errors->any())
    <div x-data="{ open: true }" x-show="open" x-transition
         class="mb-4 flex items-start justify-between gap-3 rounded-md border border-signal-red/30 bg-signal-red-soft px-4 py-3 text-sm text-signal-red">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" @click="open = false" aria-label="Tutup notifikasi" class="text-signal-red/60 hover:text-signal-red">&times;</button>
    </div>
@endif
