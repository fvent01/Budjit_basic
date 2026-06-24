<?php
$pageTitle = 'Calendar';
$monthIncome = 0; $monthExpenses = 0;
foreach ($byDate as $events) {
    foreach ($events as $ev) {
        if ($ev['event_type'] === 'income')  $monthIncome   += floatval($ev['amount'] ?? 0);
        if ($ev['event_type'] === 'expense') $monthExpenses += floatval($ev['amount'] ?? 0);
        if ($ev['event_type'] === 'bill')    $monthExpenses += floatval($ev['amount'] ?? 0);
        if ($ev['event_type'] === 'debt')    $monthExpenses += floatval($ev['amount'] ?? 0);
    }
}
$monthNet = $monthIncome - $monthExpenses;
$calJson = [];
foreach ($byDate as $date => $events) {
    $calJson[$date] = array_map(fn($ev) => [
        'id'      => $ev['id'] ?? null, 'linked_id' => $ev['linked_id'] ?? null,
        'title'   => $ev['title'],      'type'      => $ev['event_type'],
        'amount'  => isset($ev['amount'])  ? floatval($ev['amount'])  : null,
        'is_paid' => isset($ev['is_paid']) ? (bool)$ev['is_paid']     : null,
        'url'     => BASE_URL . ($ev['url'] ?? '/calendar'),
    ], $events);
}
?>
<div class="cal-toolbar">
  <span class="cal-toolbar-title">Calendar</span>
  <div class="cal-toolbar-nav">
    <a href="<?= BASE_URL ?>/calendar/<?= $prevYear ?>/<?= sprintf('%02d', $prevMonth) ?>" class="btn btn-sm"><i class="ti ti-chevron-left"></i></a>
    <a href="<?= BASE_URL ?>/calendar/<?= date('Y') ?>/<?= date('m') ?>" class="btn btn-sm">Today</a>
    <a href="<?= BASE_URL ?>/calendar/<?= $nextYear ?>/<?= sprintf('%02d', $nextMonth) ?>" class="btn btn-sm"><i class="ti ti-chevron-right"></i></a>
    <div class="cal-picker">
      <select id="cal-month-select" onchange="navigateCalendar()">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
        <?php endfor; ?>
      </select>
      <select id="cal-year-select" onchange="navigateCalendar()">
        <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
  <button onclick="document.getElementById('add-reminder-modal').style.display='flex'" class="btn btn-sm btn-primary">
    <i class="ti ti-plus"></i> Add reminder
  </button>
</div>
<div class="cal-summary-strip">
  <div class="cal-summary-item">
    <span class="cal-summary-label">Income</span>
    <span class="cal-summary-val cal-green">+<?= number_format($monthIncome, 2) ?></span>
  </div>
  <div class="cal-summary-sep"></div>
  <div class="cal-summary-item">
    <span class="cal-summary-label">Expenses &amp; Bills</span>
    <span class="cal-summary-val cal-red">-<?= number_format($monthExpenses, 2) ?></span>
  </div>
  <div class="cal-summary-sep"></div>
  <div class="cal-summary-item">
    <span class="cal-summary-label">Net</span>
    <span class="cal-summary-val <?= $monthNet >= 0 ? 'cal-green' : 'cal-red' ?>">
      <?= $monthNet >= 0 ? '+' : '-' ?><?= number_format(abs($monthNet), 2) ?>
    </span>
  </div>
</div>
<?php
$upcoming = array_filter($reminders, fn($r) => !$r['is_dismissed'] && $r['remind_date'] <= date('Y-m-d', strtotime('+3 days')));
foreach ($upcoming as $rem): ?>
  <div class="alert-bar" style="margin-bottom:8px;">
    <i class="ti ti-bell" style="color:var(--amber-dark);"></i>
    <span style="flex:1;"><?= htmlspecialchars($rem['title']) ?> - <?= date('M j', strtotime($rem['remind_date'])) ?></span>
    <form method="POST" action="<?= BASE_URL ?>/reminders/<?= $rem['id'] ?>/dismiss" style="display:inline;">
      <?= Auth::csrfField() ?><button class="btn btn-sm" style="font-size:10px;">Dismiss</button>
    </form>
  </div>
<?php endforeach; ?>
<div class="card">
  <div class="cal-grid-header">
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
      <div class="cal-dow"><?= $d ?></div>
    <?php endforeach; ?>
  </div>
  <div class="cal-grid">
    <?php for ($i = 1; $i < $startDow; $i++): ?>
      <div class="cal-cell cal-empty"></div>
    <?php endfor; ?>
    <?php for ($day = 1; $day <= $daysInMonth; $day++):
      $dateStr   = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $isToday   = $dateStr === date('Y-m-d');
      $dayEvents = $byDate[$dateStr] ?? [];
      $dayNet = 0;
      foreach ($dayEvents as $ev) {
          if ($ev['event_type'] === 'income')  $dayNet += floatval($ev['amount'] ?? 0);
          if ($ev['event_type'] === 'expense') $dayNet -= floatval($ev['amount'] ?? 0);
          if ($ev['event_type'] === 'bill')    $dayNet -= floatval($ev['amount'] ?? 0);
          if ($ev['event_type'] === 'debt')    $dayNet -= floatval($ev['amount'] ?? 0);
      }
    ?>
      <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?>" data-date="<?= $dateStr ?>" onclick="openDayPanel('<?= $dateStr ?>')">
        <div class="cal-day-row">
          <div class="cal-day-num"><?= $day ?></div>
          <?php if ($dayNet != 0): ?>
            <div class="cal-day-net <?= $dayNet >= 0 ? 'net-pos' : 'net-neg' ?>"><?= $dayNet >= 0 ? '+' : '-' ?><?= number_format(abs($dayNet), 0) ?></div>
          <?php endif; ?>
        </div>
        <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
          <div class="cal-event cal-event-<?= htmlspecialchars($ev['event_type']) ?>" onclick="event.stopPropagation();openDayPanel('<?= $dateStr ?>')">
            <?= htmlspecialchars(mb_strimwidth($ev['title'], 0, 18, '...')) ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($dayEvents) > 3): ?><div class="cal-more">+<?= count($dayEvents) - 3 ?> more</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
  <div class="cal-legend">
    <span class="cal-event cal-event-bill">Bill</span>
    <span class="cal-event cal-event-expense">Expense</span>
    <span class="cal-event cal-event-income">Income</span>
    <span class="cal-event cal-event-debt">Debt</span>
    <span class="cal-event cal-event-reminder">Reminder</span>
  </div>
</div>
<div class="card" style="margin-top:14px;">
  <div class="card-header">
    <span class="card-title">Reminders</span>
    <a href="<?= BASE_URL ?>/reminders" class="card-link">Manage all &rarr;</a>
  </div>
  <?php $active = array_filter($reminders, fn($r) => !$r['is_dismissed']); ?>
  <?php if (empty($active)): ?>
    <p class="empty-state">No active reminders.</p>
  <?php else: ?>
    <div class="reminders-row">
      <?php foreach ($active as $rem): ?>
        <div class="reminder-chip">
          <i class="ti ti-bell" style="color:var(--amber);font-size:15px;flex-shrink:0;"></i>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:500;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($rem['title']) ?></div>
            <div style="font-size:10px;color:var(--text-tertiary);"><?= date('M j', strtotime($rem['remind_date'])) ?></div>
          </div>
          <form method="POST" action="<?= BASE_URL ?>/reminders/<?= $rem['id'] ?>/delete" onsubmit="return confirm('Delete reminder?')">
            <?= Auth::csrfField() ?><button class="action-link text-red" style="font-size:11px;">&#x2715;</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
  <div style="margin-top:12px;padding-top:12px;border-top:0.5px solid var(--border);">
    <button id="push-btn" class="btn" style="font-size:12px;"><i class="ti ti-bell-ringing"></i> Enable push notifications</button>
  </div>
  <?php endif; ?>
</div>
<div id="day-panel-overlay" onclick="closeDayPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:200;"></div>
<div id="day-panel" style="display:none;position:fixed;top:0;right:0;height:100%;width:400px;max-width:100vw;background:var(--bg-primary);border-left:1px solid var(--border-strong);z-index:201;flex-direction:column;transition:transform .22s ease;transform:translateX(100%);">
  <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border);">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;">
      <div>
        <div id="panel-date-label" style="font-size:16px;font-weight:700;color:var(--text-primary);"></div>
        <div id="panel-net-label" style="font-size:12px;margin-top:3px;font-weight:600;"></div>
      </div>
      <button onclick="closeDayPanel()" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text-tertiary);line-height:1;padding:0;">&#x00D7;</button>
    </div>
  </div>
  <div id="panel-body" style="flex:1;overflow-y:auto;padding:4px 20px 12px;"></div>
  <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-secondary);">
    <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-tertiary);margin-bottom:8px;">Add to this day</div>
    <div style="display:flex;gap:7px;flex-wrap:wrap;">
      <button class="btn btn-sm" onclick="addFromPanel('expense')"><i class="ti ti-receipt"></i> Expense</button>
      <button class="btn btn-sm" onclick="addFromPanel('income')"><i class="ti ti-cash"></i> Income</button>
      <button class="btn btn-sm" onclick="addFromPanel('reminder')"><i class="ti ti-bell"></i> Reminder</button>
      <button class="btn btn-sm" onclick="addFromPanel('bill')"><i class="ti ti-file-invoice"></i> Bill</button>
    </div>
  </div>
</div>
<div id="add-reminder-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:300;align-items:center;justify-content:center;">
  <div style="background:var(--bg-primary);border-radius:var(--radius-lg);padding:24px;width:100%;max-width:440px;margin:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h2 style="font-size:16px;font-weight:600;">Add reminder</h2>
      <button onclick="document.getElementById('add-reminder-modal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text-tertiary);">&#x00D7;</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/reminders">
      <?= Auth::csrfField() ?>
      <div class="form-group"><label>Title</label><input type="text" name="title" placeholder="e.g. Pay electricity bill" required></div>
      <div class="form-group"><label>Remind date</label><input id="reminder-date-input" type="date" name="remind_date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group">
        <label>Notify via</label>
        <div style="display:flex;gap:12px;">
          <label style="display:flex;align-items:center;gap:5px;font-size:13px;font-weight:400;"><input type="checkbox" name="channels[]" value="inapp" checked> In-app</label>
          <label style="display:flex;align-items:center;gap:5px;font-size:13px;font-weight:400;"><input type="checkbox" name="channels[]" value="email"> Email</label>
          <label style="display:flex;align-items:center;gap:5px;font-size:13px;font-weight:400;"><input type="checkbox" name="channels[]" value="push"> Push</label>
        </div>
      </div>
      <div class="form-group"><label>Notes <span class="label-hint">(optional)</span></label><textarea name="notes" rows="2"></textarea></div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
        <button type="button" onclick="document.getElementById('add-reminder-modal').style.display='none'" class="btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save reminder</button>
      </div>
    </form>
  </div>
</div>
<style>
.cal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
.cal-toolbar-title{font-size:17px;font-weight:700;color:var(--text-primary);white-space:nowrap;}
.cal-toolbar-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.cal-picker{display:flex;align-items:center;gap:2px;background:var(--bg-secondary);border:0.5px solid var(--border);border-radius:var(--radius-md);padding:0 8px;height:32px;}
.cal-picker select{background:var(--bg-secondary);color:var(--text-primary);border:none;outline:none;font-size:13px;font-weight:500;cursor:pointer;padding:0 2px;font-family:var(--font);-webkit-appearance:auto;appearance:auto;}
.cal-picker select option{background:var(--bg-primary);color:var(--text-primary);}
.cal-summary-strip{display:flex;align-items:center;background:var(--bg-secondary);border:0.5px solid var(--border);border-radius:var(--radius-md);padding:10px 20px;margin-bottom:14px;}
.cal-summary-item{display:flex;flex-direction:column;align-items:center;flex:1;gap:2px;}
.cal-summary-label{font-size:10px;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:.04em;}
.cal-summary-val{font-size:15px;font-weight:700;}
.cal-green{color:var(--green-dark);}
.cal-red{color:var(--red-dark);}
.cal-summary-sep{width:1px;height:28px;background:var(--border);margin:0 12px;}
.cal-grid-header{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;margin-bottom:4px;}
.cal-dow{text-align:center;font-size:10px;font-weight:500;color:var(--text-tertiary);padding:4px 0;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;}
.cal-cell{min-height:90px;padding:5px;border-radius:var(--radius-sm);border:0.5px solid var(--border);background:var(--bg-primary);cursor:pointer;transition:background .12s,border-color .12s;}
.cal-cell:hover{background:var(--bg-secondary);border-color:var(--border-strong);}
.cal-cell.cal-empty{background:transparent!important;border-color:transparent!important;cursor:default;}
.cal-today{background:var(--green-light);border-color:var(--green);}
.cal-today:hover{filter:brightness(.97);}
.cal-cell.cal-selected{outline:2px solid var(--blue);outline-offset:-1px;}
.cal-day-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;}
.cal-day-num{font-size:12px;font-weight:500;color:var(--text-secondary);}
.cal-today .cal-day-num{color:var(--green-dark);font-weight:700;}
.cal-day-net{font-size:8px;font-weight:600;padding:1px 4px;border-radius:3px;line-height:1.4;}
.net-pos{background:var(--green-light);color:var(--green-dark);}
.net-neg{background:var(--red-light);color:var(--red-dark);}
.cal-event{display:block;font-size:9px;padding:1px 4px;border-radius:3px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;}
.cal-event-bill{background:var(--amber-light);color:var(--amber-dark);}
.cal-event-expense{background:var(--red-light);color:var(--red-dark);}
.cal-event-income{background:var(--green-light);color:var(--green-dark);}
.cal-event-debt{background:var(--purple-light);color:var(--purple-dark);}
.cal-event-reminder{background:var(--blue-light);color:var(--blue-dark);}
.cal-more{font-size:9px;color:var(--text-tertiary);}
.cal-legend{display:flex;flex-wrap:wrap;gap:10px;padding:10px 4px 0;border-top:0.5px solid var(--border);margin-top:10px;}
.cal-legend .cal-event{margin:0;padding:1px 6px;cursor:default;}
.reminders-row{display:flex;flex-wrap:wrap;gap:9px;}
.reminder-chip{display:flex;align-items:center;gap:7px;padding:7px 10px;background:var(--bg-secondary);border-radius:var(--radius-md);flex:1;min-width:200px;max-width:360px;}
#day-panel.panel-open{transform:translateX(0)!important;}
.panel-section-title{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-tertiary);margin:14px 0 6px;}
.panel-event-row{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:var(--radius-sm);background:var(--bg-secondary);margin-bottom:5px;}
.panel-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-bill{background:var(--amber);}.dot-expense{background:var(--red);}.dot-income{background:var(--green);}.dot-debt{background:var(--purple);}.dot-reminder{background:var(--blue);}
.panel-event-title{flex:1;font-size:13px;color:var(--text-primary);font-weight:500;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.panel-event-amount{font-size:12px;font-weight:600;white-space:nowrap;flex-shrink:0;}
.panel-actions{display:flex;gap:4px;flex-shrink:0;}
.panel-btn{font-size:10px;padding:2px 8px;border-radius:4px;border:0.5px solid var(--border);background:var(--bg-primary);color:var(--text-secondary);cursor:pointer;text-decoration:none;line-height:1.7;display:inline-block;}
.panel-btn:hover{background:var(--bg-tertiary);color:var(--text-primary);}
.panel-btn-del{color:var(--red-dark)!important;border-color:var(--red-light)!important;}
.panel-btn-del:hover{background:var(--red-light)!important;}
.panel-badge{font-size:9px;padding:1px 5px;border-radius:3px;margin-left:5px;font-weight:500;}
.badge-paid{background:var(--green-light);color:var(--green-dark);}
.badge-unpaid{background:var(--bg-tertiary);color:var(--text-tertiary);}
.panel-empty{font-size:13px;color:var(--text-tertiary);text-align:center;padding:28px 0 8px;}
</style>
<script>
var BASE_URL=<?= json_encode(BASE_URL) ?>;
var CSRF_TOKEN=<?= json_encode(Auth::csrfToken()) ?>;
var CAL_DATA=<?= json_encode($calJson,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
var activePanelDate=null;
var TYPE_ORDER=['income','expense','bill','debt','reminder'];
var TYPE_LABELS={income:'Income',expense:'Expenses',bill:'Bills',debt:'Debt Payments',reminder:'Reminders'};
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmt(n){return '$'+Math.abs(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function navigateCalendar(){var m=String(document.getElementById('cal-month-select').value).padStart(2,'0'),y=document.getElementById('cal-year-select').value;window.location.href=BASE_URL+'/calendar/'+y+'/'+m;}
function openDayPanel(dateStr){
  document.querySelectorAll('.cal-selected').forEach(function(c){c.classList.remove('cal-selected');});
  var cell=document.querySelector('[data-date="'+dateStr+'"]');
  if(cell)cell.classList.add('cal-selected');
  activePanelDate=dateStr;
  var events=CAL_DATA[dateStr]||[];
  var d=new Date(dateStr+'T00:00:00');
  document.getElementById('panel-date-label').textContent=d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
  var net=0;
  events.forEach(function(ev){if(ev.type==='income')net+=ev.amount||0;if(ev.type==='expense')net-=ev.amount||0;if(ev.type==='bill')net-=ev.amount||0;if(ev.type==='debt')net-=ev.amount||0;});
  var netEl=document.getElementById('panel-net-label');
  if(events.length&&net!==0){netEl.textContent='Daily net: '+(net>=0?'+':'-')+fmt(net);netEl.style.color=net>=0?'var(--green-dark)':'var(--red-dark)';}
  else{netEl.textContent='';}
  var body=document.getElementById('panel-body');
  if(!events.length){body.innerHTML='<div class="panel-empty">Nothing scheduled.<br>Use the buttons below to add an entry.</div>';}
  else{
    var grouped={};
    TYPE_ORDER.forEach(function(t){grouped[t]=[];});
    events.forEach(function(ev){if(grouped[ev.type])grouped[ev.type].push(ev);});
    var html='';
    TYPE_ORDER.forEach(function(type){
      var group=grouped[type];if(!group.length)return;
      html+='<div class="panel-section-title">'+TYPE_LABELS[type]+'</div>';
      group.forEach(function(ev){
        var amt=ev.amount!=null?'<span class="panel-event-amount" style="color:'+(type==='income'?'var(--green-dark)':'var(--red-dark)')+';">'+(type==='income'?'+':'-')+fmt(ev.amount)+'</span>':'';
        var paid=ev.is_paid===true?'<span class="panel-badge badge-paid">paid</span>':ev.is_paid===false?'<span class="panel-badge badge-unpaid">unpaid</span>':'';
        var editBtn='<a href="'+esc(ev.url)+'" class="panel-btn">Edit</a>';
        var delBtn='';
        if(ev.id&&(type==='expense'||type==='income'||type==='reminder')){
          var dp=type==='expense'?'/expenses/'+ev.id+'/delete':type==='income'?'/income/'+ev.id+'/delete':'/reminders/'+ev.id+'/delete';
          delBtn='<button class="panel-btn panel-btn-del" onclick="deleteEntry(\''+dp+'\',\''+esc(ev.title).replace(/\x27/g,"\\'")+'\')">✕</button>';
        }
        html+='<div class="panel-event-row"><div class="panel-dot dot-'+type+'"></div><span class="panel-event-title">'+esc(ev.title)+paid+'</span>'+amt+'<div class="panel-actions">'+editBtn+delBtn+'</div></div>';
      });
    });
    body.innerHTML=html;
  }
  var overlay=document.getElementById('day-panel-overlay'),panel=document.getElementById('day-panel');
  overlay.style.display='block';panel.style.display='flex';
  requestAnimationFrame(function(){panel.classList.add('panel-open');});
}
function closeDayPanel(){
  document.querySelectorAll('.cal-selected').forEach(function(c){c.classList.remove('cal-selected');});
  var panel=document.getElementById('day-panel'),overlay=document.getElementById('day-panel-overlay');
  panel.classList.remove('panel-open');
  setTimeout(function(){panel.style.display='none';overlay.style.display='none';},230);
  activePanelDate=null;
}
function addFromPanel(type){
  var date=activePanelDate||<?= json_encode(date('Y-m-d')) ?>;
  if(type==='reminder'){document.getElementById('reminder-date-input').value=date;document.getElementById('add-reminder-modal').style.display='flex';return;}
  var paths={expense:'/expenses/create',income:'/income/create',bill:'/recurring-bills/create'};
  window.location.href=BASE_URL+paths[type]+'?date='+date;
}
async function deleteEntry(path,title){
  if(!confirm('Delete "'+title+'"?'))return;
  try{
    var res=await fetch(BASE_URL+path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf_token='+encodeURIComponent(CSRF_TOKEN)});
    if(res.ok||res.redirected)window.location.reload();else alert('Delete failed.');
  }catch(e){alert('Network error.');}
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDayPanel();});
<?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
document.getElementById('push-btn').addEventListener('click',async function(){
  if(!('serviceWorker'in navigator)||!('PushManager'in window)){alert('Push not supported.');return;}
  try{
    var perm=await Notification.requestPermission();
    if(perm!=='granted'){alert('Permission denied.');return;}
    var reg=await navigator.serviceWorker.register(BASE_URL+'/sw.js');
    var sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:<?= json_encode(VAPID_PUBLIC_KEY) ?>});
    await fetch(BASE_URL+'/reminders/push-subscribe',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF_TOKEN},sub.toJSON()))});
    this.textContent='✓ Push notifications enabled';this.disabled=true;
  }catch(e){alert('Could not enable: '+e.message);}
});
<?php endif; ?>
</script>
