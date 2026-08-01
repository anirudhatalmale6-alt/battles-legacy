<?php
require __DIR__ . '/../src/bootstrap.php';
$root = config('root_person');
page_head('Family Tree', ['full' => true]);
?>
<div class="treewrap">
  <div class="treebar">
    <div class="tt"><span id="statline" class="sub">Loading family…</span></div>
    <div class="search"><input id="q" placeholder="Search relatives…" autocomplete="off"><div class="results" id="results"></div></div>
    <div class="tools">
      <button onclick="expandAll()">Expand all</button>
      <button onclick="focusOn(ROOT_DEFAULT)">Reset</button>
    </div>
  </div>
  <div class="stagewrap">
    <div id="viewport"><svg id="edges"></svg><div id="stage"></div></div>
    <div class="hint">✋ Drag to pan · scroll / pinch to zoom · click a name to open · ⌄ to expand children</div>
    <div class="zoombtns">
      <button onclick="zoomBy(1.2)">+</button><button onclick="zoomBy(.83)">−</button><button onclick="fitView()">⤢</button>
    </div>
    <div class="detail" id="detail">
      <button class="close" onclick="hideDetail()">×</button>
      <div class="dav" id="dav"></div>
      <h2 id="dname"></h2>
      <div class="dyr" id="dyr"></div>
      <div id="dfacts"></div>
      <div class="rel" id="drel"></div>
      <a class="focusbtn" id="profilebtn" href="#">◈ View full profile &amp; photos</a>
      <button class="focusbtn" onclick="focusOn(SEL)" style="background:linear-gradient(180deg,#4a141a,#360d11)">Center the tree here</button>
      <div class="note" id="dnote"></div>
    </div>
  </div>
</div>

<div id="lightbox" onclick="closeLightbox()"><span class="x" onclick="closeLightbox()">×</span><img id="lightbox-img" onclick="event.stopPropagation()" src="" alt=""></div>

<style>
.treewrap{flex:1;display:flex;flex-direction:column;min-height:0}
.treebar{display:flex;align-items:center;gap:16px;padding:10px 20px;background:rgba(28,6,8,.5);border-bottom:1px solid rgba(201,162,75,.25);flex-wrap:wrap}
.treebar .sub{font-size:14px;color:var(--gold-soft)}
.search{position:relative;margin-left:auto}
.search input{background:#1c0608;border:1px solid rgba(201,162,75,.5);color:var(--cream);padding:9px 14px;border-radius:6px;width:230px;font-family:inherit;font-size:15px}
.results{position:absolute;top:110%;left:0;right:0;background:#2b090c;border:1px solid var(--gold);border-radius:8px;max-height:280px;overflow:auto;z-index:40;display:none;box-shadow:0 12px 30px rgba(0,0,0,.6)}
.results.show{display:block}
.results div{padding:9px 13px;cursor:pointer;font-size:15px;border-bottom:1px solid rgba(201,162,75,.15)}
.results div:hover{background:var(--wine);color:var(--gold2)}
.results .yr{color:#a08b6a;font-size:13px}
.tools{display:flex;gap:8px}
.tools button{border:1px solid rgba(201,162,75,.5);background:rgba(58,13,18,.7);color:var(--gold2);padding:8px 12px;border-radius:6px;font-size:13px;cursor:pointer}
.tools button:hover{background:var(--gold);color:var(--maroon)}
.stagewrap{flex:1;position:relative;overflow:hidden}
#viewport{position:absolute;inset:0;cursor:grab;touch-action:none}
#viewport.grabbing{cursor:grabbing}
#stage{position:absolute;top:0;left:0;transform-origin:0 0}
#edges{position:absolute;top:0;left:0;pointer-events:none;overflow:visible}
.hint{position:absolute;left:16px;bottom:14px;font-size:13px;color:var(--gold-soft);opacity:.85;z-index:5;background:rgba(28,6,8,.6);padding:6px 12px;border-radius:20px}
.zoombtns{position:absolute;right:16px;bottom:14px;display:flex;gap:8px;z-index:6}
.zoombtns button{width:40px;height:40px;border-radius:50%;border:1px solid var(--gold);background:rgba(43,9,12,.85);color:var(--gold2);font-size:20px;cursor:pointer}
.zoombtns button:hover{background:var(--gold);color:var(--maroon)}
.node{position:absolute;width:170px;transform:translateX(-50%);background:linear-gradient(180deg,#fbf4e2,#efe1c0);color:var(--ink);border:1px solid var(--gold);border-radius:12px;padding:12px 10px 10px;text-align:center;box-shadow:0 8px 18px rgba(0,0,0,.45);cursor:pointer}
.node:hover{box-shadow:0 12px 24px rgba(0,0,0,.6)}
.node.sel{box-shadow:0 0 0 3px var(--gold2),0 12px 24px rgba(0,0,0,.6)}
.node.root{border-color:var(--gold2)}
.node .av{width:56px;height:56px;border-radius:50%;margin:0 auto 7px;border:2.5px solid var(--gold);background:#5c1a1f;display:grid;place-items:center;font-family:'Cormorant Garamond';font-size:22px;color:#fbeecb;overflow:hidden}
.node .av img{width:100%;height:100%;object-fit:cover}
.node .av.clickable{cursor:zoom-in;transition:transform .12s}
.node .av.clickable:hover{transform:scale(1.08)}
.node .nm{font-family:'Cormorant Garamond';font-weight:600;font-size:17px;line-height:1.05;color:var(--wine)}
.node .yr{font-size:12.5px;color:var(--muted);margin-top:2px}
.node .sp{font-size:12px;color:#8a5a2a;margin-top:3px;font-style:italic;line-height:1.15}
.caret{position:absolute;left:50%;bottom:-13px;transform:translateX(-50%);width:26px;height:26px;border-radius:50%;background:var(--wine);color:var(--gold2);border:1px solid var(--gold);font-size:14px;display:grid;place-items:center;cursor:pointer;z-index:3}
.caret:hover{background:var(--gold);color:var(--maroon)}
.caret .n{font-size:10px;position:absolute;top:-7px;right:-15px;background:var(--maroon);color:var(--gold2);border-radius:8px;padding:0 5px;border:1px solid var(--gold)}
.gen-badge{position:absolute;left:6px;top:6px;font-size:10px;background:var(--wine);color:var(--gold2);border-radius:6px;padding:1px 5px;opacity:.85}
.detail{position:absolute;top:0;right:0;height:100%;width:330px;background:linear-gradient(180deg,#340f14,#240709);border-left:1px solid rgba(201,162,75,.4);box-shadow:-8px 0 30px rgba(0,0,0,.5);transform:translateX(100%);transition:transform .3s;z-index:15;overflow-y:auto;padding:22px 20px}
.detail.show{transform:translateX(0)}
.detail .close{position:absolute;top:12px;right:14px;background:none;border:0;color:var(--gold-soft);font-size:24px;cursor:pointer}
.detail .dav{width:92px;height:92px;border-radius:50%;margin:6px auto 12px;border:3px solid var(--gold);background:#5c1a1f;display:grid;place-items:center;font-family:'Cormorant Garamond';font-size:34px;color:#fbeecb;overflow:hidden}
.detail .dav img{width:100%;height:100%;object-fit:cover}
.detail .dav.clickable{cursor:zoom-in}
.detail h2{font-family:'Cormorant Garamond';color:var(--gold2);text-align:center;font-size:27px;line-height:1.05;margin:0}
.detail .dyr{text-align:center;color:var(--gold-soft);margin-bottom:16px;font-size:15px}
.detail .fact{margin-bottom:12px;border-left:2px solid rgba(201,162,75,.4);padding-left:12px}
.detail .fact .k{font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:var(--gold)}
.detail .fact .v{font-size:15.5px;color:var(--cream)}
.rel h4{font-family:'Cormorant Garamond';color:var(--gold2);font-size:18px;margin:16px 0 6px;border-bottom:1px solid rgba(201,162,75,.25);padding-bottom:3px}
.detail .chip{cursor:pointer}
.focusbtn{display:block;width:100%;margin-top:12px;background:linear-gradient(180deg,#d8b662,#c9a24b);color:var(--maroon);border:1px solid var(--gold);padding:12px;border-radius:8px;font-family:'Cormorant Garamond';font-size:17px;letter-spacing:1px;cursor:pointer;text-align:center;font-weight:600}
.focusbtn:hover{transform:translateY(-1px)}
.note{margin-top:14px;font-size:13px;color:var(--gold-soft);line-height:1.4;border-top:1px solid rgba(201,162,75,.2);padding-top:10px}
@media(max-width:720px){.search input{width:150px}.detail{width:100%;top:auto;bottom:0;height:72%;transform:translateY(100%);border-left:0;border-top:1px solid var(--gold);border-radius:16px 16px 0 0}.detail.show{transform:translateY(0)}}
</style>

<script src="data.php?v=<?= time() ?>"></script>
<script>
const I = GED.indi, F = GED.fam;
const PHOTOS = window.PHOTOS || {}, FULL = window.FULL || {};
const ROOT_DEFAULT = <?= json_encode($root) ?>;
let ROOT = ROOT_DEFAULT, expanded = new Set([ROOT]), SEL = null;

document.getElementById('statline').textContent =
  Object.keys(I).length + " relatives · " + Object.keys(F).length + " families" + (window.IS_MEMBER ? " · signed in as family" : " · public preview");

function childrenOf(pid){const seen=new Set(),out=[];(I[pid]?.fams||[]).forEach(fid=>{(F[fid]?.chil||[]).forEach(c=>{if(I[c]&&!seen.has(c)){seen.add(c);out.push(c);}});});out.sort(byAge);return out;}
function spousesOf(pid){const out=[];(I[pid]?.fams||[]).forEach(fid=>{const f=F[fid];if(!f)return;const s=f.husb===pid?f.wife:f.husb;if(s&&I[s])out.push(s);});return out;}
function parentsOf(pid){const out=[];(I[pid]?.famc||[]).forEach(fid=>{const f=F[fid];if(!f)return;if(f.husb&&I[f.husb])out.push(f.husb);if(f.wife&&I[f.wife])out.push(f.wife);});return out;}
function yr(d){if(!d)return"";const m=d.match(/\d{4}/);return m?m[0]:"";}
/* sort people oldest-first by birth year; unknown years go last, then alphabetical */
function byAge(a,b){const ya=+(yr(I[a]?.birth?.date)||99999),yb=+(yr(I[b]?.birth?.date)||99999);return ya!==yb?ya-yb:(I[a]?.name||'').localeCompare(I[b]?.name||'');}
function lifespan(p){const b=yr(p.birth.date),d=yr(p.death.date);if(b&&d)return b+" – "+d;if(b)return "b. "+b;if(d)return "d. "+d;return "";}
function initials(p){const parts=(p.given||'?').replace(/[`'"]/g,'').trim().split(/\s+/);const a=(parts[0]||'?')[0];const b=(p.surname||'')[0]||'';return (a+b).toUpperCase();}
function avatarHTML(pid){const ph=PHOTOS[pid];return ph?`<img src="${ph}" alt="">`:`<span>${initials(I[pid])}</span>`;}

const NW=170,NH=104,HGAP=26,VGAP=66;
let nodes=[],edges=[],bounds={w:0,h:0};
function layout(){
  nodes=[];edges=[];let cursor=0;const vis=new Set();
  function place(pid,depth){
    if(vis.has(pid)){const x=cursor;cursor+=NW+HGAP;return x;}
    vis.add(pid);
    const kids=expanded.has(pid)?childrenOf(pid):[];
    let x;
    if(kids.length===0){x=cursor;cursor+=NW+HGAP;}
    else{const xs=kids.map(k=>place(k,depth+1));x=(xs[0]+xs[xs.length-1])/2;}
    nodes.push({pid,x,y:depth*(NH+VGAP),depth,kids:childrenOf(pid).length,open:expanded.has(pid)});
    return x;
  }
  place(ROOT,0);
  const pos={};nodes.forEach(n=>pos[n.pid]=n);edges=[];
  nodes.forEach(n=>{if(!n.open)return;childrenOf(n.pid).forEach(k=>{if(pos[k])edges.push([n.x,n.y,pos[k].x,pos[k].y]);});});
  let maxX=0,maxY=0;nodes.forEach(n=>{maxX=Math.max(maxX,n.x);maxY=Math.max(maxY,n.y);});
  bounds={w:maxX+NW,h:maxY+NH};render();
}
function render(){
  const stage=document.getElementById('stage'),svg=document.getElementById('edges');
  stage.innerHTML='';svg.setAttribute('width',bounds.w+NW);svg.setAttribute('height',bounds.h+NH);
  let paths='';edges.forEach(([x1,y1,x2,y2])=>{const midY=y1+NH+(VGAP/2);paths+=`<path d="M ${x1} ${y1+NH} V ${midY} H ${x2} V ${y2}" fill="none" stroke="rgba(201,162,75,.55)" stroke-width="1.6"/>`;});
  svg.innerHTML=paths;
  nodes.forEach(n=>{
    const p=I[n.pid];const el=document.createElement('div');
    el.className='node'+(n.pid===ROOT?' root':'')+(n.pid===SEL?' sel':'');
    el.style.left=n.x+'px';el.style.top=n.y+'px';
    const sp=spousesOf(n.pid).map(s=>I[s].name).slice(0,1);
    el.innerHTML=`<div class="gen-badge">Gen ${n.depth+1}</div>
      <div class="av${PHOTOS[n.pid]?' clickable':''}" title="${PHOTOS[n.pid]?'Click to enlarge':''}">${avatarHTML(n.pid)}</div>
      <div class="nm">${p.name}</div><div class="yr">${lifespan(p)||'&nbsp;'}</div>
      ${sp.length?`<div class="sp">m. ${sp[0]}</div>`:''}
      ${n.kids>0?`<div class="caret" title="${n.open?'Collapse':'Expand'} ${n.kids} children">${n.open?'▴':'▾'}<span class="n">${n.kids}</span></div>`:''}`;
    el.addEventListener('click',e=>{
      if(e.target.closest('.caret'))return;
      if(PHOTOS[n.pid]&&e.target.closest('.av')){e.stopPropagation();openLightbox(n.pid);return;}
      selectPerson(n.pid);
    });
    const car=el.querySelector('.caret');
    if(car)car.addEventListener('click',e=>{e.stopPropagation();toggle(n.pid);});
    stage.appendChild(el);
  });
}
function toggle(pid){if(expanded.has(pid))expanded.delete(pid);else expanded.add(pid);layout();}
function expandAll(){let added=true,guard=0;while(added&&guard<40){added=false;guard++;nodes.forEach(n=>{if(n.kids>0&&!expanded.has(n.pid)){expanded.add(n.pid);added=true;}});layout();}fitView();}
function focusOn(pid){if(!I[pid])return;ROOT=pid;expanded=new Set([pid]);SEL=pid;layout();showDetail(pid);fitView();}

let scale=1,tx=0,ty=0,drag=false,sx,sy;
const vp=document.getElementById('viewport'),stage=document.getElementById('stage'),svg=document.getElementById('edges');
function apply(){const t=`translate(${tx}px,${ty}px) scale(${scale})`;stage.style.transform=t;svg.style.transform=t;}
function zoomBy(f){scale=Math.min(2.2,Math.max(.18,scale*f));apply();}
function fitView(){const vw=vp.clientWidth,vh=vp.clientHeight;const s=Math.min(1.1,(vw-60)/Math.max(bounds.w,1),(vh-60)/Math.max(bounds.h,1));scale=Math.max(.18,s);tx=(vw-bounds.w*scale)/2+NW*scale/2;ty=30;apply();}
vp.addEventListener('mousedown',e=>{drag=true;sx=e.clientX-tx;sy=e.clientY-ty;vp.classList.add('grabbing');});
window.addEventListener('mousemove',e=>{if(!drag)return;tx=e.clientX-sx;ty=e.clientY-sy;apply();});
window.addEventListener('mouseup',()=>{drag=false;vp.classList.remove('grabbing');});
vp.addEventListener('wheel',e=>{e.preventDefault();zoomBy(e.deltaY<0?1.1:.9);},{passive:false});
let pd=null;
vp.addEventListener('touchstart',e=>{if(e.touches.length===1){drag=true;sx=e.touches[0].clientX-tx;sy=e.touches[0].clientY-ty;}},{passive:true});
vp.addEventListener('touchmove',e=>{if(e.touches.length===2){e.preventDefault();const d=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);if(pd)zoomBy(d/pd);pd=d;}else if(drag&&e.touches.length===1){tx=e.touches[0].clientX-sx;ty=e.touches[0].clientY-sy;apply();}},{passive:false});
vp.addEventListener('touchend',()=>{drag=false;pd=null;});

function selectPerson(pid){SEL=pid;render();showDetail(pid);}
function showDetail(pid){
  const p=I[pid];SEL=pid;
  const dav=document.getElementById('dav');dav.innerHTML=avatarHTML(pid);
  const hasPhoto=!!PHOTOS[pid];dav.classList.toggle('clickable',hasPhoto);dav.onclick=hasPhoto?()=>openLightbox(pid):null;
  document.getElementById('dname').textContent=p.name;
  document.getElementById('dyr').textContent=lifespan(p)||'Dates unknown';
  let facts='';
  if(p.birth.date||p.birth.place)facts+=fact('Born',[p.birth.date,p.birth.place].filter(Boolean).join(' · '));
  if(p.death.date||p.death.place)facts+=fact('Died',[p.death.date,p.death.place].filter(Boolean).join(' · '));
  if(p.burial&&(p.burial.date||p.burial.place))facts+=fact('Buried',[p.burial.date,p.burial.place].filter(Boolean).join(' · '));
  if(p.occupation&&p.occupation.length)facts+=fact('Occupation',p.occupation.join('; '));
  if(p.sex)facts+=fact('Sex',p.sex==='M'?'Male':p.sex==='F'?'Female':p.sex);
  if(p.living&&!window.IS_MEMBER)facts+='<div class="fact"><div class="k">Living relative</div><div class="v" style="font-size:14px">Hidden in the public preview. Sign in as family to see full details.</div></div>';
  document.getElementById('dfacts').innerHTML=facts||'<div class="fact"><div class="v">No further details yet — a great place for a family memory.</div></div>';
  let rel='';const par=parentsOf(pid),sp=spousesOf(pid),ch=childrenOf(pid);
  if(par.length)rel+=relBlock('Parents',par);
  if(sp.length)rel+=relBlock('Spouse',sp);
  if(ch.length)rel+=relBlock('Children ('+ch.length+')',ch);
  document.getElementById('drel').innerHTML=rel;
  const pb=document.getElementById('profilebtn');
  pb.href='person.php?pid='+encodeURIComponent(pid);
  pb.style.display=(window.IS_MEMBER||!p.living)?'block':'none';
  document.getElementById('dnote').textContent = window.IS_MEMBER
    ? 'Open the full profile to see every photo, or add one of your own.'
    : 'Photos and memories attach to each person once family members log in.';
  document.getElementById('detail').classList.add('show');
}
function fact(k,v){return `<div class="fact"><div class="k">${k}</div><div class="v">${v}</div></div>`;}
function relBlock(t,ids){return `<h4>${t}</h4>`+ids.map(id=>`<span class="chip" onclick="jump('${id}')">${I[id].name}${yr(I[id].birth.date)?' <span style="opacity:.6">('+yr(I[id].birth.date)+')</span>':''}</span>`).join('');}
function jump(pid){if(!nodes.find(n=>n.pid===pid)){focusOn(pid);}else{expanded.add(pid);layout();selectPerson(pid);}}
function hideDetail(){document.getElementById('detail').classList.remove('show');}

function openLightbox(pid){const src=FULL[pid]||PHOTOS[pid];if(!src)return;document.getElementById('lightbox-img').src=src;document.getElementById('lightbox').classList.add('show');}
function closeLightbox(){document.getElementById('lightbox').classList.remove('show');document.getElementById('lightbox-img').src='';}
window.addEventListener('keydown',e=>{if(e.key==='Escape')closeLightbox();});

const q=document.getElementById('q'),results=document.getElementById('results');
const roster=Object.values(I).map(p=>({id:p.id,name:p.name,y:yr(p.birth.date)}));
q.addEventListener('input',()=>{const s=q.value.trim().toLowerCase();if(s.length<2){results.classList.remove('show');return;}const hits=roster.filter(r=>r.name.toLowerCase().includes(s)).sort((a,b)=>{const ya=+(a.y||99999),yb=+(b.y||99999);return ya!==yb?ya-yb:a.name.localeCompare(b.name);}).slice(0,40);results.innerHTML=hits.map(h=>`<div onclick="pick('${h.id}')">${h.name} <span class="yr">${h.y?'· '+h.y:''}</span></div>`).join('')||'<div>No matches</div>';results.classList.add('show');});
q.addEventListener('blur',()=>setTimeout(()=>results.classList.remove('show'),200));
function pick(pid){q.value=I[pid].name;results.classList.remove('show');focusOn(pid);}

layout();fitView();showDetail(ROOT);
</script>
<?php page_foot();
