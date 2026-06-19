<?php
// ussd_sim.php — a local phone simulator to demo the USSD service (ussd.php)
// without a real gateway. Mirrors how Africa's Talking would call your endpoint.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Simulator | AgriConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins','sans-serif'] } } } }</script>
</head>
<body class="bg-akagera font-sans min-h-screen flex flex-col items-center justify-center p-4">

    <h1 class="text-white font-black text-2xl mb-1">AgriConnect <span class="text-savannah">USSD</span></h1>
    <p class="text-green-200/70 text-xs font-bold uppercase tracking-widest mb-6">Works on any basic phone — no internet</p>

    <div class="bg-gray-900 rounded-[2.5rem] p-3 shadow-2xl w-[300px] border-4 border-gray-700">
        <div class="bg-gray-100 rounded-[2rem] overflow-hidden">
            <div class="bg-gray-800 text-white text-[10px] font-bold flex justify-between px-4 py-1.5">
                <span>AgriConnect</span><span>📶 🔋</span>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Caller number</label>
                    <input id="phone" value="0781111111" class="w-full text-sm font-bold p-2 border border-gray-300 rounded-lg outline-none focus:border-akagera">
                </div>

                <div id="screen" class="bg-white border border-gray-200 rounded-xl p-3 min-h-[160px] text-sm font-medium text-gray-800 whitespace-pre-line shadow-inner">Press “Dial” to start (*384*1234#)</div>

                <input id="reply" placeholder="Type a number…" class="w-full text-center text-lg font-black tracking-widest p-2 mt-3 border border-gray-300 rounded-lg outline-none focus:border-akagera" />

                <div class="grid grid-cols-3 gap-2 mt-3">
                    <button onclick="dial()" class="col-span-1 bg-akagera text-white font-black py-2.5 rounded-lg text-xs">Dial</button>
                    <button onclick="send()" class="col-span-1 bg-savannah text-akagera font-black py-2.5 rounded-lg text-xs">Send</button>
                    <button onclick="reset()" class="col-span-1 bg-gray-200 text-gray-600 font-black py-2.5 rounded-lg text-xs">End</button>
                </div>
            </div>
        </div>
    </div>

    <p class="text-green-200/60 text-[10px] font-bold mt-6 max-w-xs text-center">Try caller 0781111111 (farmer) → option 3, or 0782222222 (buyer) → option 2. Option 1 (prices) works for anyone.</p>

    <script>
        let textChain = [];   // accumulated USSD input
        const sessionId = 'sim-' + Date.now();

        async function call() {
            const body = new URLSearchParams({
                sessionId, serviceCode: '*384*1234#',
                phoneNumber: document.getElementById('phone').value,
                text: textChain.join('*'),
            });
            const res = await fetch('ussd.php', { method: 'POST', body });
            const txt = await res.text();
            render(txt);
        }
        function render(txt) {
            const screen = document.getElementById('screen');
            const isEnd = txt.startsWith('END');
            screen.textContent = txt.replace(/^(CON|END)\s/, '');
            screen.classList.toggle('text-gray-400', false);
            document.getElementById('reply').disabled = isEnd;
            if (isEnd) document.getElementById('reply').value = '';
        }
        function dial() { textChain = []; call(); }
        function send() {
            const v = document.getElementById('reply').value.trim();
            if (v === '') return;
            textChain.push(v);
            document.getElementById('reply').value = '';
            call();
        }
        function reset() {
            textChain = [];
            document.getElementById('screen').textContent = 'Press “Dial” to start (*384*1234#)';
            document.getElementById('reply').value = '';
            document.getElementById('reply').disabled = false;
        }
    </script>
</body>
</html>
