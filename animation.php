<?php 
// หน้าเว็บนี้ทำหน้าที่แสดงผลอย่างเดียว ไม่มีการใช้ cURL ฝั่ง PHP แล้ว
$is_render = isset($_GET['mode']) && $_GET['mode'] === 'render';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Video Render - Election Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;600;800;900&display=swap');
        body { font-family: 'Anuphan', sans-serif; background-color: #f8fafc; margin: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; height: 100vh; }
        #capture-area { width: 1920px; height: 1080px; background: radial-gradient(circle at 50% 0%, #ffffff 0%, #f1f5f9 100%); position: relative; transform-origin: center; transform: scale(min(calc(100vw / 1920), calc(100vh / 1080))); display: flex; flex-direction: column; justify-content: center; align-items: center; overflow: hidden; }
        #loader, #recording-status { position: fixed; inset: 0; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.8s ease; }
        #loader { background: #ffffff; }
        #recording-status { background: rgba(255, 255, 255, 0.95); display: none; backdrop-filter: blur(10px); }
        .spinner { width: 60px; height: 60px; border: 6px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .anim-item { opacity: 0; transform: translateY(100px) scale(0.9); transition: all 1s cubic-bezier(0.25, 1.2, 0.3, 1); pointer-events: none; }
        .anim-item.show { opacity: 1; transform: translateY(0) scale(1); }
        .candidate-card { width: 600px; height: 600px; border-radius: 3.5rem; position: relative; overflow: hidden; background-color: #fff; border: 1px solid #e2e8f0; box-shadow: 0 20px 40px rgba(0,0,0,0.05); transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.6s ease; }
        .candidate-card.pressing { transform: scale(0.92) translateY(10px) !important; box-shadow: 0 5px 15px rgba(0,0,0,0.02) !important; transition: transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important; }
        .candidate-card.is-active { box-shadow: 0 40px 80px -20px rgba(0,0,0,0.15); border-color: transparent; }
        .candidate-card.is-active.glow-1 { box-shadow: 0 40px 90px -20px rgba(124, 58, 237, 0.3); }
        .candidate-card.is-active.glow-2 { box-shadow: 0 40px 90px -20px rgba(234, 88, 12, 0.3); }
        .image-container { position: absolute; inset: 0; z-index: 10; -webkit-mask-image: linear-gradient(to bottom, black 80%, transparent 100%); mask-image: linear-gradient(to bottom, black 80%, transparent 100%); }
        .layer-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.05; filter: brightness(1.5) saturate(0.2); transition: all 1s ease-out; }
        .layer-front { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); height: 90%; object-fit: contain; z-index: 20; filter: brightness(1.2) saturate(0.5); transition: all 0.8s ease-out; }
        .candidate-card.is-active .layer-bg { opacity: 1; filter: brightness(1) saturate(1.2); transform: scale(1.08); }
        .candidate-card.is-active .layer-front { filter: brightness(1) saturate(1) drop-shadow(0 25px 50px rgba(0,0,0,0.2)); transform: translateX(-50%) scale(1.08); }
        #finish-sweep { position: absolute; inset: 0; z-index: 999999; pointer-events: none; overflow: hidden; display: none; }
        .sweep-beam { position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(135deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.7) 45%, rgba(255,255,255,1) 50%, rgba(255,255,255,0.7) 55%, rgba(255,255,255,0) 100%); transform: translateX(-100%); }
        #action-ui { position: fixed; bottom: 2rem; right: 2rem; z-index: 10000; }
    </style>
</head>
<body>

    <div id="loader" style="<?php echo $is_render ? 'display:none !important;' : ''; ?>">
        <div class="spinner mb-6 border-blue-500"></div>
        <h2 class="text-3xl font-black tracking-widest uppercase text-blue-600">กำลังจัดเตรียมข้อมูล...</h2>
    </div>

    <div id="recording-status" style="<?php echo $is_render ? 'display:none !important;' : ''; ?>">
        <div class="spinner mb-6 border-indigo-600"></div>
        <h2 class="text-5xl font-black tracking-tighter text-slate-900 mb-4">กำลังเรนเดอร์ผ่าน Cloud API</h2>
        <p class="text-indigo-600 font-bold text-2xl mb-2 animate-pulse" id="render-msg">ระบบรับคำสั่งแล้ว... โปรดรอสักครู่</p>
    </div>

    <div id="action-ui" class="flex gap-4" style="<?php echo $is_render ? 'display:none !important;' : ''; ?>">
        <button onclick="runSequence()" class="bg-white border border-slate-200 text-slate-800 hover:bg-slate-50 px-6 py-3 rounded-full font-bold shadow-lg transition">
            <i class="fas fa-play mr-2 text-blue-500"></i> เล่นทดสอบ
        </button>
        <button onclick="startCloudRecord()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-full font-bold shadow-xl transition flex items-center">
            <i class="fas fa-cloud-upload-alt mr-2"></i> สร้างวิดีโอ (JSON2Video)
        </button>
    </div>

    <div id="capture-area">
        <div id="finish-sweep"><div class="sweep-beam" id="sweep-beam"></div></div>

        <div class="text-center mb-8 anim-item" id="header-title" style="z-index: 50; position: relative;">
            <p class="text-2xl text-slate-500 font-black tracking-[0.2em] mb-2 uppercase">ผลการเลือกตั้งสภานักเรียน</p>
            <h1 class="text-6xl font-black text-slate-900 tracking-tighter mb-4 drop-shadow-sm">โรงเรียนด่านขุนทด</h1>
            <div class="inline-block bg-green-50 border border-green-200 px-6 py-2 rounded-full shadow-sm">
                <p class="text-xl text-green-600 font-bold tracking-widest uppercase"><i class="fas fa-check-circle mr-2"></i>ผลคะแนนอย่างเป็นทางการ</p>
            </div>
        </div>

        <div class="flex flex-col items-center justify-center w-full gap-10">
            <div class="flex gap-16 w-full justify-center">
                <div class="candidate-card anim-item" id="card-1">
                    <div class="image-container">
                        <img src="https://votedkt.ct.ws/assets/images/logo_party1.png" class="layer-bg">
                        <img src="https://votedkt.ct.ws/assets/images/candidate1.png" class="layer-front">
                    </div>
                    <div class="absolute inset-0 z-30 flex flex-col justify-between p-8 pointer-events-none">
                        <div class="w-16 h-16 bg-purple-600 text-white rounded-2xl flex items-center justify-center text-4xl font-black shadow-lg">1</div>
                        <div class="bg-white/95 backdrop-blur-md rounded-[2rem] p-6 text-center shadow-xl border border-slate-100">
                            <h2 class="text-3xl font-black text-slate-900 mb-2">Gen Z Vision</h2>
                            <p class="text-purple-600 font-bold uppercase tracking-widest mb-4 text-xs">คะแนนรวมที่ได้</p>
                            <p class="text-6xl font-black text-slate-900 tracking-tighter" id="val-score1">0</p>
                        </div>
                    </div>
                </div>

                <div class="candidate-card anim-item" id="card-2">
                    <div class="image-container">
                        <img src="https://votedkt.ct.ws/assets/images/logo_party2.png" class="layer-bg">
                        <img src="https://votedkt.ct.ws/assets/images/candidate2.png" class="layer-front">
                    </div>
                    <div class="absolute inset-0 z-30 flex flex-col justify-between p-8 pointer-events-none">
                        <div class="w-16 h-16 bg-orange-600 text-white rounded-2xl flex items-center justify-center text-4xl font-black shadow-lg">2</div>
                        <div class="bg-white/95 backdrop-blur-md rounded-[2rem] p-6 text-center shadow-xl border border-slate-100">
                            <h2 class="text-3xl font-black text-slate-900 mb-2">DKT RISE UP</h2>
                            <p class="text-orange-600 font-bold uppercase tracking-widest mb-4 text-xs">คะแนนรวมที่ได้</p>
                            <p class="text-6xl font-black text-slate-900 tracking-tighter" id="val-score2">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full max-w-6xl grid grid-cols-5 gap-6 anim-item" id="summary-panel">
                <div class="bg-white border border-slate-200 rounded-3xl p-6 text-center shadow-sm">
                    <p class="text-slate-400 text-xs font-black uppercase tracking-widest mb-2">ผู้มีสิทธิ์</p>
                    <p class="text-4xl font-black text-slate-800" id="val-eligible">0</p>
                </div>
                <div class="bg-blue-50 border border-blue-100 rounded-3xl p-6 text-center shadow-sm">
                    <p class="text-blue-500 text-xs font-black uppercase tracking-widest mb-2">มาใช้สิทธิ์</p>
                    <p class="text-4xl font-black text-blue-700" id="val-turnout">0</p>
                </div>
                <div class="bg-indigo-50 border border-indigo-100 rounded-3xl p-6 text-center shadow-sm">
                    <p class="text-indigo-500 text-xs font-black uppercase tracking-widest mb-2">คิดเป็นร้อยละ</p>
                    <p class="text-4xl font-black text-indigo-700" id="val-percent">0%</p>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 rounded-3xl p-6 text-center shadow-sm">
                    <p class="text-emerald-500 text-xs font-black uppercase tracking-widest mb-2">บัตรดี</p>
                    <p class="text-4xl font-black text-emerald-600" id="val-valid">0</p>
                </div>
                <div class="bg-rose-50 border border-rose-100 rounded-3xl p-6 text-center shadow-sm">
                    <div class="flex justify-center gap-4 text-xs font-black uppercase tracking-widest mb-2">
                        <span class="text-rose-500">เสีย</span><span class="text-slate-300">|</span><span class="text-slate-500">ไม่ลงคะแนน</span>
                    </div>
                    <div class="flex justify-center gap-4 text-4xl font-black">
                        <span class="text-rose-600" id="val-invalid">0</span><span class="text-slate-300">-</span><span class="text-slate-600" id="val-novote">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const delay = ms => new Promise(res => setTimeout(res, ms));

        // URL เชื่อมต่อไปยังโฮสต์เก่า (InfinityFree) สำหรับสั่งเรนเดอร์
        const API_RENDER_URL = 'https://votedkt.ct.ws/api_render.php';

        async function fetchAndPopulateData() {
            // ฝังข้อมูลผลคะแนนล่าสุด (Hardcoded)
            const data = {
                "total_eligible": "2,898",
                "score1": "1,281",
                "score2": "809",
                "total_turnout": "2,335",
                "percent_turnout": "80.57",
                "valid_votes": "2,090",
                "invalid_votes": "76",
                "no_votes": "169"
            };
            
            // นำข้อมูลไปแสดงผลในหน้าเว็บ
            document.getElementById('val-eligible').innerText = data.total_eligible;
            document.getElementById('val-score1').innerText = data.score1;
            document.getElementById('val-score2').innerText = data.score2;
            document.getElementById('val-turnout').innerText = data.total_turnout;
            document.getElementById('val-percent').innerText = data.percent_turnout + '%';
            document.getElementById('val-valid').innerText = data.valid_votes;
            document.getElementById('val-invalid').innerText = data.invalid_votes;
            document.getElementById('val-novote').innerText = data.no_votes;
        }

        window.addEventListener('load', async () => {
            await fetchAndPopulateData();
            <?php if ($is_render): ?>
                setTimeout(() => { runSequence(); }, 1000);
            <?php else: ?>
                setTimeout(() => {
                    document.getElementById('loader').style.opacity = '0';
                    setTimeout(() => { document.getElementById('loader').style.display = 'none'; runSequence(); }, 800);
                }, 500); 
            <?php endif; ?>
        });

        function resetAnimation() {
            document.querySelectorAll('.anim-item').forEach(el => el.classList.remove('show', 'pressing', 'is-active', 'glow-1', 'glow-2'));
            document.getElementById('finish-sweep').style.display = 'none';
        }

        async function runSequence() {
            resetAnimation();
            await delay(400); document.getElementById('header-title').classList.add('show');
            await delay(800);
            const card1 = document.getElementById('card-1'); card1.classList.add('show');
            await delay(1000); card1.classList.add('pressing'); 
            await delay(250); card1.classList.remove('pressing'); card1.classList.add('is-active', 'glow-1'); 
            
            await delay(1000);
            const card2 = document.getElementById('card-2'); card2.classList.add('show');
            await delay(1000); card2.classList.add('pressing'); 
            await delay(250); card2.classList.remove('pressing'); card2.classList.add('is-active', 'glow-2'); 
            
            await delay(1000); document.getElementById('summary-panel').classList.add('show');
            
            await delay(3000);
            const container = document.getElementById('finish-sweep');
            const beam = document.getElementById('sweep-beam');
            container.style.display = 'block';
            let start = null;
            function step(timestamp) {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                const percent = Math.min(progress / 1200, 1);
                const xPos = -100 + (percent * 200);
                beam.style.transform = `translateX(${xPos}%)`;
                if (progress < 1200) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        async function startCloudRecord() {
            document.getElementById('action-ui').style.display = 'none';
            document.getElementById('recording-status').style.display = 'flex';
            
            // เตรียม URL ของหน้า AwardSpace ปัจจุบัน เพื่อส่งให้บอทของ JSON2Video วิ่งมาถ่าย
            const targetUrl = encodeURIComponent(window.location.href.split('?')[0] + '?mode=render');

            try {
                // ยิงคำสั่งไปหาไฟล์ api_render.php ที่ InfinityFree แทน
                const response = await fetch(`${API_RENDER_URL}?action=render&url=${targetUrl}`);
                const data = await response.json();
                
                if (data.project) {
                    document.getElementById('render-msg').innerText = "รอการประมวลผลวิดีโอ... (คิวคลาวด์)";
                    pollStatus(data.project); 
                } else {
                    throw new Error("API ไม่ตอบกลับ Project ID");
                }
            } catch (err) {
                alert("เกิดข้อผิดพลาดในการยิงคำสั่งข้ามโฮสต์: " + err.message);
                document.getElementById('action-ui').style.display = 'flex';
                document.getElementById('recording-status').style.display = 'none';
            }
        }

        async function pollStatus(projectId) {
            try {
                // เช็คสถานะผ่านไฟล์ที่อยู่บน InfinityFree 
                const res = await fetch(`${API_RENDER_URL}?action=check_status&project=${projectId}`);
                const data = await res.json();
                
                if (data.movie && data.movie.status === 'done') {
                    document.getElementById('render-msg').innerText = "เสร็จสิ้น! กำลังบันทึกไฟล์ลงเครื่อง...";
                    document.getElementById('render-msg').classList.replace('text-indigo-600', 'text-emerald-500');
                    document.getElementById('render-msg').classList.remove('animate-pulse');
                    
                    const a = document.createElement('a');
                    a.href = data.movie.url;
                    a.download = 'Election_Result_Cloud_1080p.mp4';
                    document.body.appendChild(a);
                    a.click();
                    
                    setTimeout(() => {
                        document.getElementById('recording-status').style.display = 'none';
                        document.getElementById('action-ui').style.display = 'flex';
                    }, 3000);

                } else if (data.movie && (data.movie.status === 'error' || data.movie.status === 'failed')) {
                    alert("การเรนเดอร์ล้มเหลว! สาเหตุ: " + (data.movie.message || "ระบบ Cloud ขัดข้อง"));
                    document.getElementById('recording-status').style.display = 'none';
                    document.getElementById('action-ui').style.display = 'flex';

                } else {
                    setTimeout(() => pollStatus(projectId), 4000);
                }
            } catch (err) {
                console.error("Poll Error:", err);
                // ถ้าเน็ตหลุดกระตุก ให้ลองเช็คใหม่เรื่อยๆ ไม่ให้มันหยุด
                setTimeout(() => pollStatus(projectId), 4000); 
            }
        }
    </script>
</body>
</html>
