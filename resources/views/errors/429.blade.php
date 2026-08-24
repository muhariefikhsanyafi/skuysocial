<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sabar Dulu, Skuyer! - 429</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-red-400 min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-yellow-300 border-8 border-black p-8 sm:p-12 shadow-[12px_12px_0px_0px_rgba(0,0,0,1)] max-w-2xl text-center rotate-[-1deg]">
        <h1 class="text-7xl sm:text-9xl font-black text-black mb-4 tracking-tighter">429</h1>
        
        <div class="bg-white border-4 border-black p-4 mb-6 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] inline-block">
            <h2 class="text-2xl sm:text-3xl font-black uppercase">Jari Kamu Terlalu Cepat!</h2>
        </div>
        
        <p class="text-xl font-bold text-gray-900 mb-8 border-b-4 border-black pb-4">
            Sistem pertahanan kami mendeteksi aktivitas beruntun (spam). Tolong rem sedikit jarinya dan tunggu 1 menit sebelum mengirim status atau komentar lagi.
        </p>

        <a href="{{ url()->previous() }}" class="inline-block bg-cyan-400 border-4 border-black px-8 py-4 font-black uppercase text-xl shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[6px] hover:translate-y-[6px] transition-all text-black">
            KEMBALI KE JALAN YANG BENAR
        </a>
    </div>

</body>
</html>