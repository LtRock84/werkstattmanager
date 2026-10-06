// Isolated UI fixture server. Never packaged or used by the Nextcloud application.
import { createServer } from 'node:http'
import { readFileSync } from 'node:fs'
import { randomUUID } from 'node:crypto'
const now=Math.floor(Date.now()/1000)
const base=(id)=>({id,version:1,created_at:now-3600,updated_at:now})
const db={
 vehicles:[{...base('v1'),kind:'fleet',plate:'B · WK 204',number:'F-04',make:'Volkswagen',model:'Transporter',vin:'TEST-FIN-204',mileage:84200,mileage_date:'2026-10-01',registration:'2020-03-12',notes:'Serviceheft im Handschuhfach',archived:false},{...base('v2'),kind:'customer',plate:'B · MS 318',make:'Škoda',model:'Octavia',number:'',mileage:61500,mileage_date:'2026-10-01',notes:'',archived:false}],
 employees:[{...base('e1'),name:'Alex',archived:false},{...base('e2'),name:'Sam',archived:false},{...base('e3'),name:'Chris',archived:false}],
 templates:[{...base('t1'),title:'Inspektion',description:'Inspektion nach Wartungsplan durchführen.',steps:['Flüssigkeitsstände prüfen','Bremsanlage prüfen','Probefahrt durchführen'],minutes:60,favorite:true,position:1,archived:false},{...base('t2'),title:'Ölwechsel',description:'Motoröl und Ölfilter wechseln.',steps:['Motoröl wechseln','Ölfilter ersetzen'],minutes:30,favorite:true,position:2,archived:false},{...base('t3'),title:'Reifenwechsel',description:'Räder wechseln und Luftdruck prüfen.',steps:['Räder wechseln','Luftdruck prüfen'],minutes:45,favorite:true,position:3,archived:false}],
 snippets:[{...base('s1'),title:'Fehlerspeicher',text:'Fehlerspeicher ausgelesen',favorite:true,position:1,archived:false},{...base('s2'),title:'Probefahrt',text:'Probefahrt durchgeführt',favorite:true,position:2,archived:false}],
 orders:[{...base('a1111111111111111111111111111111'),vehicle_id:'v1',title:'Inspektion und Ölwechsel',description:'Jahresinspektion durchführen, Öl und Filter erneuern.',steps:['Flüssigkeitsstände prüfen','Ölwechsel','Probefahrt durchführen'],minutes:90,planned_start:null,due_at:new Date((now+86400)*1000).toISOString(),priority:'normal',status:'open',pause_reason:null},{...base('b2222222222222222222222222222222'),vehicle_id:'v2',title:'Bremsbeläge vorne ersetzen',description:'Geräusch beim Bremsen prüfen. Neue Beläge liegen bereit.',steps:['Bremsen prüfen','Beläge wechseln'],minutes:60,planned_start:null,due_at:null,priority:'urgent',status:'review',pause_reason:null}],
 active:[],intervals:[],parts:[{...base('p1'),order_id:'a1111111111111111111111111111111',title:'Ölfilter',quantity:1,part_number:'',note:'Passend zum Fahrzeug',status:'requested',employee_id:'e1'}],
 defects:[{...base('d1'),vehicle_id:'v1',description:'Kontrollleuchte leuchtet während der Fahrt.',reporter:'Testfahrer',status:'new',priority:'normal',public_text:'',published:false,mileage:null,mileage_confirmed:false,order_id:null}],
 deadlines:[{...base('f1'),vehicle_id:'v1',title:'Hauptuntersuchung',due_date:new Date((now+7*86400)*1000).toISOString().slice(0,10),due_km:null,warning_days:[30,7],status:'open'}],customers:[],attachments:[],audit:[],mileage:[]
}
// A long history exercises the fixed section navigation with realistic, non-production context.
db.audit=Array.from({length:24},(_,i)=>({
 ...base(`audit-${i}`),created_at:now-i*3600,vehicle_id:'v1',order_id:db.orders[0].id,employee_id:'e1',actor:'werkstatt-tablet',action:i%2?'order.finish':'part.requested',
 details:i%2?{note:'Probefahrt ohne Befund.',steps:['Öl gewechselt','Bremsanlage geprüft']}:{part_id:'p1',paused:true},
 context:{order:{id:db.orders[0].id,title:db.orders[0].title},employee:{name:'Alex'},part:i%2?null:{title:'Ölfilter',quantity:2,part_number:'TEST-FILTER',priority:'normal',note:'Passend zum Fahrzeug'},defect:null,deadline:null},
}))
const header=`<!DOCTYPE html><html lang="de"><head data-requesttoken="fixture-only"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Werkstattmanager · UI-Testdaten</title><style>:root{--color-main-background:#fff;--color-main-text:#172b29;--color-background-hover:#f2f5f4;--color-border:#dce4e2;--color-primary-element:#087c71;--color-primary-element-text:#fff;--color-primary-element-light:#e2f1ed;--color-primary-element-light-text:#12493d;--color-text-maxcontrast:#52645f;--default-font-size:15px;--default-clickable-area:44px;--default-line-height:1.5;--border-radius:8px;--border-radius-element:8px;--border-width-input:1px;--animation-quick:100ms;--font-face:system-ui}body{margin:0;font-family:system-ui}button,input,select,textarea{font:inherit}button{cursor:pointer}button:disabled{cursor:default}#fixture-banner{background:#fff2c7;padding:7px 16px;font-size:12px;color:#674800}a{color:var(--color-primary-element)}*{box-sizing:border-box}</style><link rel="stylesheet" href="/css/main.css"><script>window.OC={config:{modRewriteWorking:true},coreApps:[]};window._oc_webroot='';window._oc_appswebroots={werkstattmanager:'/apps/werkstattmanager'};window._nc_l10n_locale='de_DE';window._nc_l10n_language='de';</script></head>`
const server=createServer(async(req,res)=>{
 try{
 const u=new URL(req.url,'http://127.0.0.1:8765'),p=u.pathname.replace('/index.php','');let body='';for await(const c of req)body+=c;const parsed=body?JSON.parse(body):{};const data=parsed.data??parsed
 const json=(v,status=200)=>{res.writeHead(status,{'content-type':'application/json'});res.end(JSON.stringify(v))}
 if(p.startsWith('/apps/werkstattmanager/api/v1/records/')){const kind=p.split('/').pop();let rows=db[kind]??[];for(const[k,v]of u.searchParams){if(['offset','q'].includes(k))continue;rows=rows.filter(x=>String(x[k])===v)}const q=u.searchParams.get('q');if(q)rows=rows.filter(x=>JSON.stringify(x).toLowerCase().includes(q.toLowerCase()));const offset=Number(u.searchParams.get('offset')??0);return json({items:rows.slice(offset,offset+100),more:rows.length>offset+100,offset})}
 if(p==='/apps/werkstattmanager/api/v1/orders'&&req.method==='POST'){const o={...base(randomUUID().replaceAll('-','')),...data};delete o.request_id;db.orders.unshift(o);return json(o)}
 if(p==='/apps/werkstattmanager/public/v1/vehicle'||p==='/apps/werkstattmanager/public/v1/session')return json({vehicle:db.vehicles[0],items:[{text:'Klimaanlage kühlt zeitweise nicht. Werkstatttermin ist geplant.',status:'open',created_at:now-86400}],more:false})
 if(p==='/apps/werkstattmanager/public/v1/defects')return json({id:randomUUID(),received:true})
 if(p.endsWith('/api/v1/settings'))return json({master_group:'werkstatt-meister',tablet_group:'werkstatt-tablet',admin:true})
 if(p==='/css/main.css'||p==='/js/werkstattmanager-main.js'){res.writeHead(200,{'content-type':p.endsWith('.css')?'text/css':'text/javascript'});return res.end(readFileSync('.'+p))}
 if(p==='/'||p==='/tablet'||p==='/fahrer'){const role=p==='/tablet'?'tablet':p==='/fahrer'?'driver':'master';res.writeHead(200,{'content-type':'text/html'});return res.end(header+`<body><div id="fixture-banner">UI-TESTANSICHT · ausschließlich Beispieldaten · kein Nextcloud-Server</div><input type="hidden" id="initial-state-werkstattmanager-mode" value="${Buffer.from(JSON.stringify(role)).toString('base64')}"><div id="werkstattmanager"></div><script src="/js/werkstattmanager-main.js"></script></body></html>`)}
 json({message:'Testserver unterstützt diese Aktion nicht.'},404)
 }catch(e){res.writeHead(500);res.end('Fixture request failed')}
})
server.listen(8765,'127.0.0.1',()=>console.log('Isolated UI fixture server: http://127.0.0.1:8765'))
