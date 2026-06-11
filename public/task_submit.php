
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Submit Task — <?= htmlspecialchars($task['title']) ?> | Umarae</title>
<script src="https://www.google.com/recaptcha/api.js?render=6LfhVa0rAAAAAI0O9gDuwEVZ3yjouu7jhaikvW64"></script>
<script src="https://openfpcdn.io/fingerprintjs/v4"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
  --bg:#03040a;--bg2:#0a0e1a;--card:#0d111f;--card2:#131b2e;
  --border:rgba(255,255,255,0.06);--border2:rgba(255,255,255,0.1);
  --primary:#4361ee;--primary2:#6b84f5;--gold:#F5A623;--gold2:#FFD580;
  --green:#22C55E;--green2:#4ADE80;--red:#EF4444;--cyan:#00d4ff;--cyan2:#22d3ee;
  --purple:#8b5cf6;--orange:#f97316;
  --text:#e8ecff;--text2:#8892b0;--text3:#4a5568;
  --shadow:0 4px 24px rgba(0,0,0,0.5);--shadow2:0 8px 32px rgba(0,0,0,0.6);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Space Grotesk',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;line-height:1.6}

/* Background */
.orb-wrap{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:0.5}
.orb-1{width:60vw;height:60vw;top:-20%;left:-15%;background:radial-gradient(circle,rgba(67,97,238,0.2),transparent 70%);animation:orbMove 20s ease-in-out infinite alternate}
.orb-2{width:50vw;height:50vw;bottom:-15%;right:-10%;background:radial-gradient(circle,rgba(245,166,35,0.15),transparent 70%);animation:orbMove 15s ease-in-out infinite alternate-reverse}
.orb-3{width:40vw;height:40vw;top:40%;left:45%;background:radial-gradient(circle,rgba(0,212,255,0.12),transparent 70%);animation:orbMove 25s ease-in-out infinite alternate}
@keyframes orbMove{0%{transform:translate(0,0) scale(1)}100%{transform:translate(30px,20px) scale(1.08)}}

.container{max-width:700px;margin:0 auto;padding:28px 20px 60px;position:relative;z-index:2}

/* Nav */
.nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:16px}
.brand{display:flex;align-items:center;gap:12px}
.brand-logo{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;font-size:16px}
.brand-text{font-size:18px;font-weight:700;letter-spacing:-0.5px;background:linear-gradient(135deg,#fff,#b8c4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.back-btn{display:inline-flex;align-items:center;gap:6px;background:var(--card);border:1px solid var(--border);color:var(--text2);padding:8px 16px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;transition:all 0.3s}
.back-btn:hover{border-color:var(--border2);color:var(--text)}

/* Task Card */
.task-card{background:linear-gradient(145deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px;position:relative;overflow:hidden}
.task-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--primary),transparent);opacity:0.6}

.task-cat{display:inline-flex;align-items:center;gap:6px;background:rgba(67,97,238,0.1);border:1px solid rgba(67,97,238,0.2);color:var(--primary2);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}

.task-title{font-size:22px;font-weight:700;line-height:1.2;margin-bottom:10px;letter-spacing:-0.3px}
.task-desc{font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:20px}

/* Reward Box */
.reward-box{display:flex;align-items:center;gap:14px;background:rgba(0,0,0,0.3);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:20px;flex-wrap:wrap}
.reward-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.reward-icon.uqx{background:rgba(0,212,255,0.1);color:var(--cyan2);border:1px solid rgba(0,212,255,0.2)}
.reward-icon.usd{background:rgba(34,197,94,0.1);color:var(--green2);border:1px solid rgba(34,197,94,0.2)}
.reward-info{flex:1;min-width:0}
.reward-label{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text3);margin-bottom:4px}
.reward-value{font-family:'JetBrains Mono',monospace;font-size:24px;font-weight:700;line-height:1}
.reward-value.uqx{color:var(--cyan2)}
.reward-value.usd{color:var(--green2)}
.reward-badge{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:8px;margin-left:auto;flex-shrink:0}
.reward-badge.uqx{background:rgba(0,212,255,0.1);color:var(--cyan2);border:1px solid rgba(0,212,255,0.2)}
.reward-badge.usd{background:rgba(34,197,94,0.1);color:var(--green2);border:1px solid rgba(34,197,94,0.2)}

/* Meta Grid */
.meta-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
@media(max-width:480px){.meta-grid{grid-template-columns:1fr 1fr}}
.meta-item{background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:14px;text-align:center}
.meta-item i{font-size:14px;color:var(--text3);margin-bottom:6px;display:block}
.meta-item .lbl{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px}
.meta-item .val{font-size:13px;font-weight:700;color:var(--text)}

/* External Link */
.ext-link{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(67,97,238,0.08),rgba(0,212,255,0.04));border:1px solid rgba(67,97,238,0.2);border-radius:12px;padding:14px 18px;text-decoration:none;color:var(--text);transition:all 0.3s;margin-bottom:24px}
.ext-link:hover{border-color:rgba(67,97,238,0.4);transform:translateY(-2px);box-shadow:0 4px 20px rgba(67,97,238,0.15)}
.ext-link i{color:var(--primary2);font-size:18px}
.ext-link-text{flex:1}
.ext-link-text .title{font-size:13px;font-weight:700;margin-bottom:2px}
.ext-link-text .url{font-size:11px;color:var(--text3);word-break:break-all}
.ext-link-arrow{color:var(--primary2);font-size:14px}

/* Form */
.form-card{background:linear-gradient(145deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:20px;padding:28px;position:relative;overflow:hidden}
.form-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:0.5}

.form-title{font-size:16px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:20px}
.form-title i{color:var(--gold);font-size:18px}

.field{margin-bottom:20px}
.field-label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text3);margin-bottom:8px}
.field-label .req{color:var(--red);margin-left:2px}

.inp{
  width:100%;padding:14px 16px;
  background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;
  color:var(--text);font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:500;
  outline:none;transition:all 0.3s;
}
.inp:focus{border-color:rgba(245,166,35,0.4);box-shadow:0 0 0 3px rgba(245,166,35,0.08);background:rgba(255,255,255,0.05)}
.inp::placeholder{color:var(--text3)}
textarea.inp{resize:vertical;min-height:120px;line-height:1.6}

/* Upload */
.upload-zone{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  width:100%;min-height:140px;
  background:rgba(255,255,255,0.02);border:2px dashed var(--border);
  border-radius:14px;cursor:pointer;transition:all 0.3s;padding:24px;
}
.upload-zone:hover{border-color:rgba(245,166,35,0.4);background:rgba(245,166,35,0.03)}
.upload-zone i{font-size:32px;color:var(--text3);margin-bottom:10px}
.upload-zone .title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px}
.upload-zone .hint{font-size:12px;color:var(--text3)}
.upload-zone input{display:none}
.upload-zone.has-file{border-color:var(--green);background:rgba(34,197,94,0.05)}
.upload-zone.has-file i{color:var(--green2)}

/* Submit */
.submit-btn{
  width:100%;padding:16px;
  background:linear-gradient(135deg,var(--gold),var(--orange));border:none;border-radius:14px;
  color:#000;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:14px;
  letter-spacing:1px;text-transform:uppercase;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:10px;
  box-shadow:0 4px 24px rgba(245,166,35,0.25);transition:all 0.3s;
}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(245,166,35,0.4)}
.submit-btn:active{transform:translateY(0)}
.submit-btn:disabled{opacity:0.5;cursor:not-allowed;transform:none;box-shadow:none}

/* Error */
.error-box{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;color:#F87171;font-size:13px;font-weight:600}
.error-box i{font-size:16px;flex-shrink:0}

/* Steps */
.steps{display:flex;gap:8px;margin-bottom:24px}
.step{flex:1;text-align:center;padding:12px 8px;background:var(--card);border:1px solid var(--border);border-radius:12px;position:relative}
.step.active{background:rgba(67,97,238,0.08);border-color:rgba(67,97,238,0.3)}
.step-num{width:24px;height:24px;border-radius:50%;background:var(--text4);color:var(--text);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;margin:0 auto 6px}
.step.active .step-num{background:var(--primary);color:#fff}
.step-title{font-size:10px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;color:var(--text3)}
.step.active .step-title{color:var(--primary2)}

/* Animations */
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.fu{animation:fadeUp 0.5s ease both}
.fu-1{animation-delay:0.05s}.fu-2{animation-delay:0.1s}.fu-3{animation-delay:0.15s}

::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--text4);border-radius:99px}
</style>
</head>
<body>
<div class="orb-wrap"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>

<div class="container">

  <!-- NAV -->
  <div class="nav fu">
    <div class="brand">
      <div class="brand-logo"><i class="fas fa-tasks"></i></div>
      <div class="brand-text">Task Submission</div>
    </div>
    <a href="tasks.php" class="back-btn"><i class="fas fa-arrow-left" style="font-size:11px"></i> Back to Tasks</a>
  </div>

  <!-- STEPS -->
  <div class="steps fu fu-1">
    <div class="step active"><div class="step-num">1</div><div class="step-title">Read Task</div></div>
    <div class="step"><div class="step-num">2</div><div class="step-title">Complete</div></div>
    <div class="step"><div class="step-num">3</div><div class="step-title">Submit Proof</div></div>
    <div class="step"><div class="step-num">4</div><div class="step-title">Get Paid</div></div>
  </div>

  <!-- TASK INFO -->
  <div class="task-card fu fu-2">
    <div class="task-cat"><i class="fas fa-tag" style="font-size:9px"></i> <?= htmlspecialchars($task['category'] ?? 'General') ?></div>
    <h1 class="task-title"><?= htmlspecialchars($task['title']) ?></h1>
    <p class="task-desc"><?= nl2br(htmlspecialchars($task['description'])) ?></p>

    <!-- Reward Box -->
    <div class="reward-box">
      <div class="reward-icon <?= $isUqx ? 'uqx' : 'usd' ?>">
        <i class="fas <?= $isUqx ? 'fa-coins' : 'fa-dollar-sign' ?>"></i>
      </div>
      <div class="reward-info">
        <div class="reward-label">Reward Amount</div>
        <div class="reward-value <?= $isUqx ? 'uqx' : 'usd' ?>"><?= $reward_display ?></div>
      </div>
      <span class="reward-badge <?= $isUqx ? 'uqx' : 'usd' ?>">
        <?= $isUqx ? '⬡ UQX TOKEN' : '💵 USD CASH' ?>
      </span>
    </div>

    <!-- Meta -->
    <div class="meta-grid">
      <div class="meta-item">
        <i class="fas fa-clock"></i>
        <div class="lbl">Deadline</div>
        <div class="val"><?= htmlspecialchars($task['deadline'] ?? 'None') ?></div>
      </div>
      <div class="meta-item">
        <i class="fas fa-check-circle"></i>
        <div class="lbl">Proof Type</div>
        <div class="val"><?= htmlspecialchars($task['proof_type'] ?? 'Screenshot') ?></div>
      </div>
      <div class="meta-item">
        <i class="fas fa-users"></i>
        <div class="lbl">Submissions</div>
        <div class="val"><?= (int)($task['submissions_count'] ?? 0) ?></div>
      </div>
    </div>

    <?php if (!empty($task['external_url'])): ?>
    <a class="ext-link" href="redirect.php?task_id=<?= (int)$task['id'] ?>" target="_blank" rel="noopener">
      <i class="fas fa-external-link-alt"></i>
      <div class="ext-link-text">
        <div class="title">Task Link</div>
        <div class="url"><?= htmlspecialchars($task['external_url']) ?></div>
      </div>
      <i class="fas fa-chevron-right ext-link-arrow"></i>
    </a>
    <?php endif; ?>
  </div>

  <!-- FORM -->
  <div class="form-card fu fu-3">
    <div class="form-title"><i class="fas fa-file-signature"></i> Submit Your Proof</div>

    <?php if(!empty($error)): ?>
    <div class="error-box"><i class="fas fa-circle-xmark"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="task-form">

      <?php if(stripos($task['proof_type'],'Screenshot')!==false): ?>
      <div class="field">
        <label class="field-label"><i class="fas fa-image" style="font-size:10px"></i> Upload Screenshot <span class="req">*</span></label>
        <label class="upload-zone" id="uploadZone">
          <i class="fas fa-cloud-arrow-up" id="uploadIcon"></i>
          <div class="title" id="uploadTitle">Click to upload screenshot</div>
          <div class="hint">JPG, PNG, WEBP — Max 5MB</div>
          <input type="file" name="proof_image" accept="image/*" onchange="handleUpload(this)">
        </label>
      </div>
      <?php endif; ?>

      <?php if(stripos($task['proof_type'],'Text')!==false): ?>
      <div class="field">
        <label class="field-label"><i class="fas fa-align-left" style="font-size:10px"></i> Proof Text <span class="req">*</span></label>
        <textarea name="proof_text" class="inp" placeholder="Paste your proof text, link, or details here..."></textarea>
      </div>
      <?php endif; ?>

      <button type="submit" class="submit-btn" id="submitBtn">
        <i class="fas fa-paper-plane" style="font-size:14px"></i>
        Submit for <?= $reward_display ?>
      </button>
    </form>
  </div>

</div>

<script>
// UUID fallback
function generateUUID(){
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c){
    const r = Math.random()*16|0, v = c==='x'?r:(r&0x3|0x8);
    return v.toString(16);
  });
}
function getStoredId(){ try{ return localStorage.getItem('umarae_device_fp')||''; }catch(e){return'';} }
function storeId(id){ try{ localStorage.setItem('umarae_device_fp',id); }catch(e){} }

window.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('task-form');
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;

  let deviceId = getStoredId();
  try{
    const fp = await FingerprintJS.load();
    const result = await fp.get();
    if(result && result.visitorId) deviceId = result.visitorId;
  }catch(e){}
  if(!deviceId || deviceId==='0') deviceId = generateUUID();
  storeId(deviceId);

  let input = form.querySelector("input[name='device_fp']");
  if(!input){
    input = document.createElement('input');
    input.type = 'hidden'; input.name = 'device_fp';
    form.appendChild(input);
  }
  input.value = deviceId;
  btn.disabled = false;

  form.addEventListener('submit', function(e){
    const fpInput = form.querySelector('input[name=device_fp]');
    if(!fpInput || !fpInput.value || fpInput.value==='0'){
      e.preventDefault(); alert("Please wait, generating device fingerprint..."); return;
    }
    grecaptcha.ready(function(){
      grecaptcha.execute('6LfhVa0rAAAAAI0O9gDuwEVZ3yjouu7jhaikvW64',{action:'task_submit'}).then(function(token){
        let recaptchaInput = document.createElement('input');
        recaptchaInput.type='hidden'; recaptchaInput.name='g-recaptcha-response'; recaptchaInput.value=token;
        form.appendChild(recaptchaInput);
        form.submit();
      });
    });
  });
});

function handleUpload(inp){
  const zone = document.getElementById('uploadZone');
  const icon = document.getElementById('uploadIcon');
  const title = document.getElementById('uploadTitle');
  if(inp.files[0]){
    zone.classList.add('has-file');
    icon.className = 'fas fa-circle-check';
    title.textContent = inp.files[0].name;
  }
}
</script>
</body>
</html>
