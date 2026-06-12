function Dashboard() {
  return (
    <div style={{display:'flex', flexDirection:'column', gap:20, maxWidth: 1100}}>
      <div style={{display:'grid', gridTemplateColumns:'1.6fr 1fr', gap:20}}>
        <SavingsHero />
        <CreditsCard />
      </div>
      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:20}}>
        <PlantCard />
        <QuickStats />
      </div>
      <BillsTable />
    </div>
  );
}

function SavingsHero() {
  return (
    <div style={{background:'var(--grad-sun)', borderRadius:24, padding:28, color:'white', position:'relative', overflow:'hidden', boxShadow:'var(--shadow-md)'}}>
      <div style={{position:'absolute', right:-40, top:-40, width:220, height:220, borderRadius:'50%', background:'rgba(255,255,255,0.15)'}}/>
      <div style={{position:'relative'}}>
        <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', opacity:0.85}}>Economia em abril</div>
        <div style={{display:'flex', alignItems:'baseline', gap:10, marginTop:10}}>
          <div style={{fontFamily:'var(--font-display)', fontWeight:900, fontSize:60, lineHeight:1, letterSpacing:'-0.02em'}}>R$ 312,40</div>
          <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:14, background:'rgba(255,255,255,0.25)', padding:'4px 10px', borderRadius:999}}>−28%</div>
        </div>
        <div style={{fontFamily:'var(--font-body)', fontSize:14, opacity:0.9, marginTop:10, maxWidth:340, lineHeight:1.5}}>
          Sua fatura de luz foi reduzida automaticamente pelos créditos da sua usina parceira.
        </div>
      </div>
    </div>
  );
}

function CreditsCard() {
  return (
    <div style={{background:'white', borderRadius:24, padding:24, boxShadow:'var(--shadow-sm)'}}>
      <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-accent-leaf-deep)'}}>Créditos de energia</div>
      <div style={{display:'flex', alignItems:'baseline', gap:6, marginTop:10}}>
        <div style={{fontFamily:'var(--font-display)', fontWeight:900, fontSize:38, color:'var(--color-ink)', letterSpacing:'-0.02em'}}>412,6</div>
        <div style={{fontFamily:'var(--font-mono)', fontSize:14, color:'var(--color-graphite)'}}>kWh</div>
      </div>
      <div style={{fontFamily:'var(--font-body)', fontSize:13, color:'var(--color-graphite)', marginTop:4}}>disponíveis para compensação</div>
      <div style={{marginTop:14, height:8, background:'var(--color-mist)', borderRadius:999, overflow:'hidden'}}>
        <div style={{width:'72%', height:'100%', background:'var(--color-accent-leaf)'}}/>
      </div>
      <div style={{display:'flex', justifyContent:'space-between', fontFamily:'var(--font-mono)', fontSize:11, color:'var(--color-slate)', marginTop:6}}>
        <span>72% utilizado</span><span>meta 570 kWh</span>
      </div>
    </div>
  );
}

function PlantCard() {
  return (
    <div style={{background:'white', borderRadius:24, padding:24, boxShadow:'var(--shadow-sm)'}}>
      <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-deep)'}}>Sua usina parceira</div>
      <div style={{display:'flex', alignItems:'center', gap:14, marginTop:14}}>
        <div style={{width:56, height:56, borderRadius:16, background:'var(--grad-sun)', display:'flex', alignItems:'center', justifyContent:'center', color:'white'}}>
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/></svg>
        </div>
        <div>
          <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:18, color:'var(--color-ink)'}}>Usina Palhoça II</div>
          <div style={{fontFamily:'var(--font-body)', fontSize:13, color:'var(--color-graphite)'}}>Palhoça, SC · Distribuidora Celesc</div>
        </div>
      </div>
      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:12, marginTop:18, paddingTop:16, borderTop:'1px solid var(--color-mist)'}}>
        <div>
          <div style={{fontFamily:'var(--font-mono)', fontSize:10, color:'var(--color-slate)', textTransform:'uppercase', letterSpacing:'0.1em'}}>Capacidade</div>
          <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:18, color:'var(--color-ink)'}}>2,4 MWp</div>
        </div>
        <div>
          <div style={{fontFamily:'var(--font-mono)', fontSize:10, color:'var(--color-slate)', textTransform:'uppercase', letterSpacing:'0.1em'}}>Geração mês</div>
          <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:18, color:'var(--color-ink)'}}>318 MWh</div>
        </div>
      </div>
    </div>
  );
}

function QuickStats() {
  const rows = [
    {t:'Economia acumulada (ano)', v:'R$ 2.847,10', tag:'+12%', tone:'success'},
    {t:'Contrato vigente', v:'24 meses', tag:'Ativo', tone:'success'},
    {t:'Próxima fatura', v:'05 mai 2026', tag:'Em 14 dias', tone:'info'},
  ];
  return (
    <div style={{background:'white', borderRadius:24, padding:8, boxShadow:'var(--shadow-sm)'}}>
      {rows.map((r,i)=>(
        <div key={i} style={{display:'flex', alignItems:'center', justifyContent:'space-between', padding:'14px 16px', borderBottom: i<rows.length-1?'1px solid var(--color-mist)':'none'}}>
          <div>
            <div style={{fontFamily:'var(--font-body)', fontSize:12, color:'var(--color-graphite)'}}>{r.t}</div>
            <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:17, color:'var(--color-ink)', marginTop:2}}>{r.v}</div>
          </div>
          <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, padding:'4px 10px', borderRadius:999, background: r.tone==='success'?'var(--color-success-soft)':'var(--color-info-soft)', color: r.tone==='success'?'var(--color-accent-leaf-deep)':'var(--color-info)'}}>{r.tag}</span>
        </div>
      ))}
    </div>
  );
}

function BillsTable() {
  const rows = [
    {m:'Abril 2026', consumo:'418 kWh', credito:'R$ 312,40', fatura:'R$ 802,60', st:'paid'},
    {m:'Março 2026', consumo:'402 kWh', credito:'R$ 298,10', fatura:'R$ 768,90', st:'paid'},
    {m:'Fevereiro 2026', consumo:'451 kWh', credito:'R$ 334,20', fatura:'R$ 861,80', st:'paid'},
    {m:'Janeiro 2026', consumo:'489 kWh', credito:'R$ 362,50', fatura:'R$ 934,10', st:'paid'},
  ];
  return (
    <div style={{background:'white', borderRadius:24, padding:24, boxShadow:'var(--shadow-sm)'}}>
      <div style={{display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:16}}>
        <div>
          <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-deep)'}}>Faturas</div>
          <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:20, color:'var(--color-ink)', marginTop:4}}>Histórico recente</div>
        </div>
        <a href="#" style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:12, color:'var(--color-primary-deep)', textDecoration:'none'}}>Ver todas →</a>
      </div>
      <table style={{width:'100%', borderCollapse:'collapse'}}>
        <thead>
          <tr style={{textAlign:'left', fontFamily:'var(--font-mono)', fontSize:10, color:'var(--color-slate)', textTransform:'uppercase', letterSpacing:'0.1em'}}>
            <th style={{padding:'10px 8px', fontWeight:600}}>Mês</th>
            <th style={{padding:'10px 8px', fontWeight:600}}>Consumo</th>
            <th style={{padding:'10px 8px', fontWeight:600}}>Crédito aplicado</th>
            <th style={{padding:'10px 8px', fontWeight:600}}>Fatura final</th>
            <th style={{padding:'10px 8px', fontWeight:600}}>Status</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r,i)=>(
            <tr key={i} style={{borderTop:'1px solid var(--color-mist)', fontFamily:'var(--font-body)', fontSize:14, color:'var(--color-ink)'}}>
              <td style={{padding:'14px 8px', fontWeight:600}}>{r.m}</td>
              <td style={{padding:'14px 8px', fontFamily:'var(--font-mono)', fontSize:13}}>{r.consumo}</td>
              <td style={{padding:'14px 8px', fontFamily:'var(--font-mono)', fontSize:13, color:'var(--color-accent-leaf-deep)', fontWeight:600}}>−{r.credito}</td>
              <td style={{padding:'14px 8px', fontFamily:'var(--font-mono)', fontSize:13, fontWeight:700}}>{r.fatura}</td>
              <td style={{padding:'14px 8px'}}>
                <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, padding:'4px 10px', borderRadius:999, background:'var(--color-success-soft)', color:'var(--color-accent-leaf-deep)', display:'inline-flex', alignItems:'center', gap:6}}>
                  <span style={{width:6, height:6, borderRadius:'50%', background:'var(--color-success)'}}/>Paga
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

window.Dashboard = Dashboard;
